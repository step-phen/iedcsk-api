<?php

namespace App\Http\Controllers;

use App\Support\ClassSession;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// The learner devices: no login; after claim/join the server session remembers {learnerId, enrollmentId, sessionId}.
class LearnerSessionController extends Controller
{
    private const PLAYABLE = ['present', 'late'];

    // Names on the picker: present or late learners (today's attendance before class, the class roster during it), minus anyone mid-activity.
    public function learners(Request $request): JsonResponse
    {
        $session = $this->currentSession($request);
        if (! $session) {
            $learners = $this->presentToday()->orderBy('learners.first_name')->get(['learners.id', 'learners.first_name', 'learners.avatar_url']);

            return response()->json(['learners' => $learners->map(fn ($l) => $this->pickerRow($l))]);
        }

        $active = ClassSession::activeActivity($session->id);
        // Mid-activity on another device, unless they already finished this activity in an earlier class (then they are done, not busy).
        $busy = $active
            ? DB::table('learner_activity_assignments')->where(['session_activity_id' => $active->session_activity_id, 'status' => 'in_progress'])
                ->whereNotIn('enrollment_id', DB::table('learner_activity_assignments')
                    ->where(['activity_id' => $active->activity_id, 'status' => 'completed'])->select('enrollment_id'))
                ->pluck('enrollment_id')
            : collect();

        $learners = DB::table('classroom_session_learners')
            ->join('learner_school_years', 'learner_school_years.id', '=', 'classroom_session_learners.enrollment_id')
            ->join('learners', 'learners.id', '=', 'learner_school_years.learner_id')
            ->where('classroom_session_learners.classroom_session_id', $session->id)
            ->whereIn('classroom_session_learners.attendance_status', self::PLAYABLE)
            ->whereNotIn('classroom_session_learners.enrollment_id', $busy)
            ->orderBy('learners.first_name')
            ->get(['learners.id', 'learners.first_name', 'learners.avatar_url']);

        return response()->json(['learners' => $learners->map(fn ($l) => $this->pickerRow($l))]);
    }

    // First name and photo only: this list is shown before anyone is identified.
    private function pickerRow(object $learner): array
    {
        return ['id' => $learner->id, 'firstName' => $learner->first_name, 'photoUrl' => $this->photoUrl($learner->avatar_url)];
    }

    // Same public-disk URL the teacher's learner list uses; null when no photo was uploaded.
    private function photoUrl(?string $path): ?string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $path ? $disk->url($path) : null;
    }

    // A child taps their name: remember who this device is for the running class.
    public function claim(Request $request): JsonResponse
    {
        $learnerId = $request->validate(['learnerId' => ['required', 'integer']])['learnerId'];
        $session = $this->currentSession($request);
        if (! $session) {
            // Before class the device waits on the profile screen; the class is attached on join.
            $enrollmentId = $this->presentToday()->where('learners.id', $learnerId)->value('learner_school_years.id');
            if (! $enrollmentId) {
                return response()->json(['message' => 'Your teacher has not marked you present yet.'], 403);
            }
            $this->remember($request, $learnerId, $enrollmentId, null);

            return response()->json(['success' => true]);
        }
        $row = $this->rosterRow($session->id, $learnerId);
        if (! $row) {
            return response()->json(['message' => 'Your teacher has not added you to the class yet.'], 403);
        }
        if (! in_array($row->attendance_status, self::PLAYABLE, true)) {
            return response()->json(['message' => 'Your teacher has not marked you present yet.'], 403);
        }

        $this->remember($request, $learnerId, $row->enrollment_id, $session->id);
        if ($row->participation_status === 'not_joined') {
            DB::table('classroom_session_learners')->where('id', $row->id)
                ->update(['participation_status' => 'joined', 'joined_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $stored = $this->stored($request);
        $learner = $stored ? DB::table('learners')->where('id', $stored['learnerId'])->first() : null;
        abort_if(! $learner, 404);

        return response()->json([
            'id' => $learner->id,
            'firstName' => $learner->first_name,
            'middleName' => $learner->middle_name,
            'lastName' => $learner->last_name,
            'gender' => $learner->gender,
            'photoUrl' => $this->photoUrl($learner->avatar_url),
        ]);
    }

    // What every learner screen polls: is there a class, what is on screen, and how far this learner got.
    public function status(Request $request): JsonResponse
    {
        $session = $this->currentSession($request);
        if (! $session) {
            return response()->json(['active' => false]);
        }

        $active = ClassSession::activeActivity($session->id);
        $participantStatus = null;
        $learnerId = $request->integer('learnerId') ?: null;
        $stored = $learnerId ? $this->stored($request, $learnerId) : null;
        if ($stored) {
            $row = $this->rosterRow($session->id, $learnerId);
            // Removed from this class, so the class is over for them.
            if (! $row) {
                return response()->json(['active' => false]);
            }
            if ($active) {
                $status = $this->completedAssignment($row->enrollment_id, $active->activity_id)
                    ? 'completed'
                    : $this->assignment($row->enrollment_id, $active->session_activity_id)?->status;
                $participantStatus = match ($status) {
                    'completed' => 'Finished',
                    'in_progress' => 'Started the Activity',
                    default => null,
                };
            }
        }

        $isDemonstration = $active?->activity_format === 'practical' && $active->demo_video_url;

        return response()->json([
            'active' => true,
            'sessionId' => $session->id,
            'moduleName' => DB::table('section_modules')->join('modules', 'modules.id', '=', 'section_modules.module_id')
                ->where('section_modules.id', $session->section_module_id)->value('modules.name'),
            'lessonId' => $active?->lesson_id,
            'lessonName' => $active?->lesson_title,
            'lessonVideoUrl' => ClassSession::fileUrl($active?->lesson_video_url),
            'activityId' => $active?->activity_id,
            'activityName' => $active?->activity_name,
            'activityCode' => $active?->activity_code,
            'activityType' => $isDemonstration ? 'demonstration' : $active?->game_type,
            'activityFormat' => $active?->activity_format,
            'activityVideoUrl' => ClassSession::fileUrl($active?->demo_video_url),
            'activityInstructions' => $active?->activity_instructions,
            'participantStatus' => $participantStatus,
            'startedAt' => Carbon::parse($session->started_at)->toIso8601String(),
        ]);
    }

    // Whether this learner already finished the activity on screen, in this class or an earlier one.
    public function completion(Request $request): JsonResponse
    {
        $stored = $this->stored($request, $request->integer('learnerId') ?: null);
        $session = $this->currentSession($request);
        $active = $session ? ClassSession::activeActivity($session->id) : null;
        $assignment = $stored && $active ? $this->completedAssignment($stored['enrollmentId'], $active->activity_id) : null;
        if (! $assignment) {
            return response()->json(['completed' => false]);
        }

        $attempt = $this->latestAttempt($assignment->id);

        return response()->json(array_filter([
            'completed' => true,
            'score' => $attempt?->score !== null ? (float) $attempt->score : null,
            'total' => $attempt?->max_score !== null ? (float) $attempt->max_score : null,
        ], fn ($v) => $v !== null));
    }

    // Start (or resume) the activity on screen; the teacher's attendance mark decides who may play.
    public function join(Request $request): JsonResponse
    {
        $learnerId = $request->validate(['learnerId' => ['required', 'integer']])['learnerId'];
        $session = $this->currentSession($request);
        if (! $session) {
            return response()->json(['message' => 'The class has not started yet.'], 400);
        }
        $row = $this->rosterRow($session->id, $learnerId);
        if (! $row) {
            return response()->json(['message' => 'Learner is not part of this class.'], 403);
        }
        if (! in_array($row->attendance_status, self::PLAYABLE, true)) {
            return response()->json(['message' => "Learner has not been marked present for today's class."], 403);
        }
        $active = ClassSession::activeActivity($session->id);
        if (! $active) {
            return response()->json(['message' => 'Your teacher has not picked an activity yet.'], 400);
        }

        $finished = DB::transaction(function () use ($session, $row, $active) {
            $now = now();
            $assignment = DB::table('learner_activity_assignments')
                ->where(['enrollment_id' => $row->enrollment_id, 'session_activity_id' => $active->session_activity_id])
                ->lockForUpdate()->first();
            // An activity is played once: finishing it in any earlier class counts too.
            if ($assignment?->status === 'completed' || $this->completedAssignment($row->enrollment_id, $active->activity_id)) {
                return true;
            }

            $assignmentId = $assignment?->id ?? DB::table('learner_activity_assignments')->insertGetId([
                'enrollment_id' => $row->enrollment_id,
                'activity_id' => $active->activity_id,
                'quarter_id' => $session->quarter_id,
                'teacher_id' => $session->teacher_id,
                'session_activity_id' => $active->session_activity_id,
                'assigned_at' => $now,
                'status' => 'in_progress',
            ]);
            if ($assignment && $assignment->status !== 'in_progress') {
                DB::table('learner_activity_assignments')->where('id', $assignmentId)->update(['status' => 'in_progress', 'updated_at' => $now]);
            }
            if (! DB::table('learner_activity_attempts')->where(['assignment_id' => $assignmentId, 'status' => 'in_progress'])->exists()) {
                DB::table('learner_activity_attempts')->insert([
                    'assignment_id' => $assignmentId,
                    'attempt_number' => DB::table('learner_activity_attempts')->where('assignment_id', $assignmentId)->max('attempt_number') + 1,
                    'status' => 'in_progress',
                    'started_at' => $now,
                ]);
            }
            DB::table('classroom_session_learners')->where('id', $row->id)->update([
                'participation_status' => 'in_progress',
                'joined_at' => $row->joined_at ?? $now,
                'updated_at' => $now,
            ]);

            return false;
        });
        if ($finished) {
            return response()->json(['message' => 'This activity has already been completed.'], 409);
        }

        $this->remember($request, $learnerId, $row->enrollment_id, $session->id);

        return response()->json(['success' => true]);
    }

    // The game sends what the child answered; the score comes from the server's own answer key.
    public function finish(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activityId' => ['required', 'integer'],
            'answers' => ['required', 'array', 'min:1', 'max:50'],
            'answers.*.questionId' => ['required', 'string', 'min:1', 'max:64'],
            'answers.*.response' => ['required', 'string', 'min:1', 'max:64'],
        ]);
        [$stored, $active, $error] = $this->playing($request, (int) $data['activityId'], 'digital');
        if ($error) {
            return $error;
        }

        $key = DB::table('activity_questions')
            ->where('activity_id', $active->activity_id)
            // Decoy items marked gradable:false never count toward score or total.
            ->whereRaw("coalesce(question_data->>'gradable', 'true') <> 'false'")
            ->pluck('correct_answer', 'question_key')
            ->map(fn ($answer) => json_decode($answer)->answer);
        if ($key->isEmpty()) {
            return response()->json(['message' => 'This activity cannot be graded.'], 400);
        }

        // First answer per question counts; ids outside the key are ignored, so padding can't raise the score.
        $given = [];
        foreach ($data['answers'] as $answer) {
            $given[$answer['questionId']] ??= $answer['response'];
        }
        $total = $key->count();
        $score = $key->filter(fn ($correct, $questionId) => ($given[$questionId] ?? null) === $correct)->count();

        return $this->complete($stored, $active, ['status' => 'evaluated', 'score' => $score, 'max_score' => $total, 'evaluated_at' => now()])
            ?? response()->json(['success' => true, 'score' => $score, 'total' => $total]);
    }

    // A practical activity's on-screen part is done; the teacher rates it later.
    public function demonstration(Request $request): JsonResponse
    {
        $data = $request->validate(['activityId' => ['required', 'integer']]);
        [$stored, $active, $error] = $this->playing($request, (int) $data['activityId'], 'practical');
        if ($error) {
            return $error;
        }

        return $this->complete($stored, $active, ['status' => 'submitted', 'demo_watched_at' => now()])
            ?? response()->json(['success' => true]);
    }

    // This learner's teacher rating for one practical activity, once it exists.
    public function practicalResult(Request $request): JsonResponse
    {
        $activityId = $request->validate(['activityId' => ['required', 'integer']])['activityId'];
        $stored = $this->stored($request);
        $rating = $stored ? DB::table('learner_activity_attempts')
            ->join('learner_activity_assignments', 'learner_activity_assignments.id', '=', 'learner_activity_attempts.assignment_id')
            ->where(['learner_activity_assignments.enrollment_id' => $stored['enrollmentId'], 'learner_activity_assignments.activity_id' => $activityId])
            ->whereNotNull('learner_activity_attempts.practical_rating')
            ->orderByDesc('learner_activity_attempts.evaluated_at')
            ->value('learner_activity_attempts.practical_rating') : null;

        return response()->json($rating ? ['rated' => true, 'result' => $rating] : ['rated' => false]);
    }

    // A game's questions: for a signed-in teacher, or a learner whose class has this activity on screen.
    public function questions(Request $request, int $activity): JsonResponse
    {
        $allowed = Auth::guard('web')->check();
        if (! $allowed && $stored = $this->stored($request)) {
            $session = $this->currentSession($request);
            $allowed = $session && $session->id === $stored['sessionId']
                && ClassSession::activeActivity($session->id)?->activity_id === $activity;
        }
        if (! $allowed) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $questions = DB::table('activity_questions')->where('activity_id', $activity)->orderBy('question_order')->get();
        if ($questions->isEmpty()) {
            return response()->json(['message' => 'No questions found for this activity.'], 404);
        }

        return response()->json($questions->map(fn ($q) => [
            'questionKey' => $q->question_key,
            'questionOrder' => $q->question_order,
            'questionData' => json_decode($q->question_data),
            'correctAnswer' => json_decode($q->correct_answer),
        ]));
    }

    // The class learner devices follow: this device's own class while it runs, else the newest running class.
    private function currentSession(Request $request): ?object
    {
        $running = fn () => DB::table('classroom_sessions')->where('status', 'ongoing');
        $stored = $this->stored($request);
        if ($stored && $session = $running()->where('id', $stored['sessionId'])->first()) {
            return $session;
        }

        // ponytail: one classroom on the LAN, so the newest running class is the class; pick by teacher or section if several ever run at once.
        return $running()->orderByDesc('started_at')->first();
    }

    // The remembered learner, optionally only when it is the given learner.
    private function stored(Request $request, ?int $learnerId = null): ?array
    {
        $stored = $request->hasSession() ? $request->session()->get('learner') : null;

        return $stored && ($learnerId === null || $stored['learnerId'] === $learnerId) ? $stored : null;
    }

    private function remember(Request $request, int $learnerId, int $enrollmentId, ?int $sessionId): void
    {
        if ($request->hasSession()) {
            $request->session()->put('learner', ['learnerId' => $learnerId, 'enrollmentId' => $enrollmentId, 'sessionId' => $sessionId]);
        }
    }

    // ponytail: every section's present learners, fine for one classroom on the LAN; filter by section if several classes share it.
    private function presentToday(): Builder
    {
        return DB::table('attendance')
            ->join('learner_school_years', 'learner_school_years.id', '=', 'attendance.enrollment_id')
            ->join('learners', 'learners.id', '=', 'learner_school_years.learner_id')
            ->where('learner_school_years.status', 'active')
            ->where('attendance.attendance_date', ClassSession::today())
            ->whereIn('attendance.status', self::PLAYABLE);
    }

    private function rosterRow(int $sessionId, int $learnerId): ?object
    {
        return DB::table('classroom_session_learners')
            ->join('learner_school_years', 'learner_school_years.id', '=', 'classroom_session_learners.enrollment_id')
            ->where('classroom_session_learners.classroom_session_id', $sessionId)
            ->where('learner_school_years.learner_id', $learnerId)
            ->first(['classroom_session_learners.*']);
    }

    private function assignment(int $enrollmentId, int $sessionActivityId): ?object
    {
        return DB::table('learner_activity_assignments')
            ->where(['enrollment_id' => $enrollmentId, 'session_activity_id' => $sessionActivityId])
            ->first();
    }

    // The learner's latest finished run of this activity in any class, or null if they never finished it.
    private function completedAssignment(int $enrollmentId, int $activityId): ?object
    {
        return DB::table('learner_activity_assignments')
            ->where(['enrollment_id' => $enrollmentId, 'activity_id' => $activityId, 'status' => 'completed'])
            ->orderByDesc('completed_at')
            ->first();
    }

    private function latestAttempt(int $assignmentId): ?object
    {
        return DB::table('learner_activity_attempts')->where('assignment_id', $assignmentId)->orderByDesc('attempt_number')->first();
    }

    // [stored learner, active activity, error response]: the learner's own class must still run with this activity on screen.
    private function playing(Request $request, int $activityId, string $format): array
    {
        $stored = $this->stored($request);
        if (! $stored) {
            return [null, null, response()->json(['message' => 'Unauthorized.'], 401)];
        }
        $session = $this->currentSession($request);
        if (! $session || $session->id !== $stored['sessionId']) {
            return [null, null, response()->json(['message' => 'Your class has changed. Please rejoin.'], 403)];
        }
        $active = ClassSession::activeActivity($session->id);
        if ($active?->activity_id !== $activityId) {
            return [null, null, response()->json(['message' => 'This activity is not currently active.'], 403)];
        }
        if ($active->activity_format !== $format) {
            return [null, null, response()->json(['message' => "This is not a {$format} activity."], 400)];
        }

        return [$stored, $active, null];
    }

    // Records the learner's attempt and completes their assignment, or returns the error response.
    private function complete(array $stored, object $active, array $attemptChanges): ?JsonResponse
    {
        return DB::transaction(function () use ($stored, $active, $attemptChanges) {
            $assignment = DB::table('learner_activity_assignments')
                ->where(['enrollment_id' => $stored['enrollmentId'], 'session_activity_id' => $active->session_activity_id])
                ->lockForUpdate()->first();
            if (! $assignment) {
                return response()->json(['message' => 'Join the activity first.'], 404);
            }
            if ($assignment->status === 'completed' || $this->completedAssignment($stored['enrollmentId'], $active->activity_id)) {
                return response()->json(['message' => 'This activity has already been completed.'], 409);
            }

            $now = now();
            $attempt = DB::table('learner_activity_attempts')->where(['assignment_id' => $assignment->id, 'status' => 'in_progress'])
                ->orderByDesc('attempt_number')->first();
            $attemptId = $attempt?->id ?? DB::table('learner_activity_attempts')->insertGetId([
                'assignment_id' => $assignment->id,
                'attempt_number' => DB::table('learner_activity_attempts')->where('assignment_id', $assignment->id)->max('attempt_number') + 1,
                'status' => 'in_progress',
                'started_at' => $now,
            ]);
            DB::table('learner_activity_attempts')->where('id', $attemptId)->update($attemptChanges + ['submitted_at' => $now, 'updated_at' => $now]);
            DB::table('learner_activity_assignments')->where('id', $assignment->id)
                ->update(['status' => 'completed', 'completed_at' => $now, 'updated_at' => $now]);
            $learner = DB::table('learners')->where('id', $stored['learnerId'])->first(['first_name', 'last_name']);
            // The bell rings for whoever owns the section now; the assignment keeps its original teacher.
            $owner = DB::table('learner_school_years')->join('sections', 'sections.id', '=', 'learner_school_years.section_id')
                ->where('learner_school_years.id', $assignment->enrollment_id)->value('sections.teacher_id');
            // The unique assignment_id makes a retried finish a no-op instead of a second alert.
            DB::table('notifications')->insertOrIgnore([
                'teacher_id' => $owner ?? $assignment->teacher_id,
                'assignment_id' => $assignment->id,
                'kind' => $active->activity_format === 'practical' ? 'needs_rating' : 'finished',
                'learner_name' => trim("{$learner?->first_name} {$learner?->last_name}"),
                'activity_name' => $active->activity_name,
                'created_at' => $now,
            ]);

            return null;
        });
    }
}
