<?php

namespace App\Http\Controllers;

use App\Http\Requests\PracticalRatingRequest;
use App\Http\Requests\SaveAttendanceRequest;
use App\Http\Requests\StartClassRequest;
use App\Models\Learner;
use App\Models\Section;
use App\Models\Teacher;
use App\Support\ClassSession;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// The teacher's side of a running class: what the Classroom page shows, start/change/end, attendance and practical ratings.
class ClassroomController extends Controller
{
    public function show(#[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);
        $open = $this->openSession($teacher);
        $session = $open && $open->section_id === $section->id ? $open : null;
        $active = $session ? ClassSession::activeActivity($session->id) : null;
        $enrollments = $this->activeEnrollments($section->id);

        return response()->json([
            'activeSession' => $session ? [
                'id' => $session->id,
                'status' => 'ACTIVE',
                'startedAt' => Carbon::parse($session->started_at)->toIso8601String(),
                'activeModule' => ['id' => $session->module_id, 'moduleName' => $session->module_name],
                'activeActivityName' => $active?->activity_name,
                'activeLessonTitle' => $active?->lesson_title,
                'activeActivityFormat' => $active?->activity_format,
            ] : null,
            'modules' => $this->modules($section->id, $section->school_year_id),
            'attendance' => (object) DB::table('attendance')
                ->whereIn('enrollment_id', $enrollments->keys())
                ->where('attendance_date', ClassSession::today())
                ->get(['enrollment_id', 'status'])
                ->mapWithKeys(fn ($a) => [$enrollments[$a->enrollment_id] => strtoupper($a->status)])
                ->all(),
            'learners' => $this->learners($section->id, $enrollments, $active),
        ]);
    }

    // Starts a class, or moves the running one to another lesson/activity (or module) of the same section.
    public function start(StartClassRequest $request, #[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);
        $data = $request->validated();

        $sectionModule = DB::table('section_modules')
            ->where(['section_id' => $section->id, 'module_id' => $data['moduleId'], 'is_active' => true])
            ->first();
        if (! $sectionModule) {
            throw ValidationException::withMessages(['moduleId' => 'This module is not part of the section.']);
        }
        $quarterId = DB::table('quarters')->where(['school_year_id' => $section->school_year_id, 'status' => 'active'])->value('id');
        if (! $quarterId) {
            throw ValidationException::withMessages(['activityId' => 'Set an active quarter in Settings first.']);
        }

        $sessionId = DB::transaction(function () use ($teacher, $section, $sectionModule, $quarterId, $data) {
            $open = $this->openSession($teacher, lock: true);
            if ($open && $open->section_id !== $section->id) {
                $name = DB::table('sections')->where('id', $open->section_id)->value('name');
                throw ValidationException::withMessages(['activityId' => "End the class in {$name} first."]);
            }
            $enrollmentIds = $this->activeEnrollments($section->id)->keys();
            $marked = DB::table('attendance')
                ->whereIn('enrollment_id', $enrollmentIds)
                ->where('attendance_date', ClassSession::today())
                ->count();
            if ($enrollmentIds->isEmpty() || $marked < $enrollmentIds->count()) {
                throw ValidationException::withMessages(['activityId' => 'Mark attendance for every learner first.']);
            }
            if ($open && $open->section_module_id !== $sectionModule->id) {
                ClassSession::end($open->id);
                $open = null;
            }

            $sessionId = $open?->id ?? $this->createSession($teacher, $section, $sectionModule->id, $quarterId);
            $this->showActivity($sessionId, (int) $data['lessonId'], (int) $data['activityId'], $teacher->id);

            return $sessionId;
        });

        $startedAt = DB::table('classroom_sessions')->where('id', $sessionId)->value('started_at');

        return response()->json(['classroomSessionId' => $sessionId, 'startedAt' => Carbon::parse($startedAt)->toIso8601String()]);
    }

    public function end(#[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);
        $open = $this->openSession($teacher);
        if (! $open || $open->section_id !== $section->id) {
            throw ValidationException::withMessages(['class' => 'No class is running in this section.']);
        }

        DB::transaction(fn () => ClassSession::end($open->id));

        return response()->json(['success' => true]);
    }

    // Today's marks go to the daily attendance record and, during a class, to that class's roster too.
    public function attendance(SaveAttendanceRequest $request, #[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);
        $byLearner = $this->activeEnrollments($section->id)->flip();
        $entries = $request->validated('entries');
        foreach ($entries as $i => $entry) {
            if (! isset($byLearner[$entry['learnerId']])) {
                throw ValidationException::withMessages(["entries.$i.learnerId" => 'This learner is not in the section.']);
            }
        }

        $open = $this->openSession($teacher);
        $sessionId = $open && $open->section_id === $section->id ? $open->id : null;
        $now = now();

        DB::transaction(function () use ($entries, $byLearner, $teacher, $sessionId, $now) {
            foreach ($entries as $entry) {
                $enrollmentId = $byLearner[$entry['learnerId']];
                $status = strtolower($entry['status']);
                DB::table('attendance')->updateOrInsert(
                    ['enrollment_id' => $enrollmentId, 'attendance_date' => ClassSession::today()],
                    ['status' => $status, 'recorded_by' => $teacher->id, 'recorded_at' => $now, 'updated_at' => $now],
                );
                if ($sessionId) {
                    DB::table('classroom_session_learners')
                        ->where(['classroom_session_id' => $sessionId, 'enrollment_id' => $enrollmentId])
                        ->update(['attendance_status' => $status, 'attendance_recorded_by' => $teacher->id, 'attendance_recorded_at' => $now, 'updated_at' => $now]);
                }
            }
        });

        return response()->json(['success' => true]);
    }

    public function practicalRating(PracticalRatingRequest $request, #[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);
        $data = $request->validated();
        $enrollmentId = $this->activeEnrollments($section->id)->flip()[$data['learnerId']] ?? null;
        if (! $enrollmentId) {
            throw ValidationException::withMessages(['learnerId' => 'This learner is not in the section.']);
        }

        $attemptId = DB::table('learner_activity_attempts')
            ->join('learner_activity_assignments', 'learner_activity_assignments.id', '=', 'learner_activity_attempts.assignment_id')
            ->join('classroom_session_activities', 'classroom_session_activities.id', '=', 'learner_activity_assignments.session_activity_id')
            ->join('classroom_session_lessons', 'classroom_session_lessons.id', '=', 'classroom_session_activities.session_lesson_id')
            ->join('classroom_sessions', 'classroom_sessions.id', '=', 'classroom_session_lessons.classroom_session_id')
            ->join('section_modules', 'section_modules.id', '=', 'classroom_sessions.section_module_id')
            ->where('section_modules.section_id', $section->id)
            ->where('learner_activity_assignments.enrollment_id', $enrollmentId)
            ->where('learner_activity_assignments.activity_id', $data['activityId'])
            ->whereNotNull('learner_activity_attempts.demo_watched_at')
            ->orderByDesc('learner_activity_attempts.id')
            ->value('learner_activity_attempts.id');
        if (! $attemptId) {
            throw ValidationException::withMessages(['result' => 'This learner has not finished the demonstration yet.']);
        }

        DB::table('learner_activity_attempts')->where('id', $attemptId)->update([
            'status' => 'evaluated',
            'practical_rating' => $data['result'],
            'remarks' => filled($data['remarks'] ?? null) ? trim($data['remarks']) : null,
            'evaluated_by' => $teacher->id,
            'evaluated_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    // This teacher's running class (at most one, enforced by the DB) with its section and module.
    private function openSession(Teacher $teacher, bool $lock = false): ?object
    {
        $query = DB::table('classroom_sessions')
            ->join('section_modules', 'section_modules.id', '=', 'classroom_sessions.section_module_id')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where('classroom_sessions.teacher_id', $teacher->id)
            ->whereIn('classroom_sessions.status', ['scheduled', 'ongoing']);

        return ($lock ? $query->lockForUpdate() : $query)->first([
            'classroom_sessions.id', 'classroom_sessions.section_module_id', 'classroom_sessions.started_at',
            'section_modules.section_id', 'modules.id as module_id', 'modules.name as module_name',
        ]);
    }

    // enrollment_id => learner_id for the section's active enrollments.
    private function activeEnrollments(int $sectionId): Collection
    {
        return DB::table('learner_school_years')
            ->where(['section_id' => $sectionId, 'status' => 'active'])
            ->pluck('learner_id', 'id');
    }

    private function createSession(Teacher $teacher, Section $section, int $sectionModuleId, int $quarterId): int
    {
        $now = Carbon::now('Asia/Manila');
        $schedule = $this->usableSchedules($section->id)
            ->where('section_modules.id', $sectionModuleId)
            ->first(['class_schedules.id', 'class_schedules.start_time', 'class_schedules.end_time']);
        // ponytail: an unscheduled class gets the next hour, capped at midnight; a class started at 23:59:59 would still fail the times check.
        $end = $now->copy()->addHour();
        $plannedEnd = $end->isSameDay($now) ? $end->format('H:i:s') : '23:59:59';

        $sessionId = DB::table('classroom_sessions')->insertGetId([
            'section_module_id' => $sectionModuleId,
            'schedule_id' => $schedule?->id,
            'quarter_id' => $quarterId,
            'teacher_id' => $teacher->id,
            'session_date' => $now->toDateString(),
            'planned_start_time' => $schedule?->start_time ?? $now->format('H:i:s'),
            'planned_end_time' => $schedule?->end_time ?? $plannedEnd,
            'started_at' => now(),
            'status' => 'ongoing',
        ]);

        // The roster starts from today's daily attendance marks.
        $enrollments = $this->activeEnrollments($section->id);
        $marks = DB::table('attendance')->whereIn('enrollment_id', $enrollments->keys())
            ->where('attendance_date', $now->toDateString())->get()->keyBy('enrollment_id');
        DB::table('classroom_session_learners')->insert($enrollments->keys()->map(fn ($enrollmentId) => [
            'classroom_session_id' => $sessionId,
            'enrollment_id' => $enrollmentId,
            'attendance_status' => $marks[$enrollmentId]->status ?? 'unmarked',
            'participation_status' => 'not_joined',
            'attendance_recorded_by' => $marks[$enrollmentId]->recorded_by ?? null,
            'attendance_recorded_at' => $marks[$enrollmentId]->recorded_at ?? null,
        ])->all());

        return $sessionId;
    }

    // Puts an activity on screen: closes the current one (and its lesson when the lesson changes).
    private function showActivity(int $sessionId, int $lessonId, int $activityId, int $teacherId): void
    {
        $now = now();
        $activeLesson = DB::table('classroom_session_lessons')->where(['classroom_session_id' => $sessionId, 'status' => 'active'])->first();
        $activeActivity = $activeLesson
            ? DB::table('classroom_session_activities')->where(['session_lesson_id' => $activeLesson->id, 'status' => 'active'])->first()
            : null;
        if ($activeActivity && $activeLesson->lesson_id === $lessonId && $activeActivity->activity_id === $activityId) {
            return;
        }

        if ($activeActivity) {
            DB::table('classroom_session_activities')->where('id', $activeActivity->id)
                ->update(['status' => 'completed', 'ended_at' => $now, 'updated_at' => $now]);
            // Learners still mid-way through the old activity leave it unfinished.
            DB::table('learner_activity_attempts')
                ->whereIn('assignment_id', DB::table('learner_activity_assignments')->where('session_activity_id', $activeActivity->id)->select('id'))
                ->where('status', 'in_progress')
                ->update(['status' => 'abandoned', 'updated_at' => $now]);
        }

        if ($activeLesson && $activeLesson->lesson_id === $lessonId) {
            $sessionLessonId = $activeLesson->id;
        } else {
            if ($activeLesson) {
                DB::table('classroom_session_lessons')->where('id', $activeLesson->id)
                    ->update(['status' => 'completed', 'ended_at' => $now, 'updated_at' => $now]);
            }
            $sessionLessonId = DB::table('classroom_session_lessons')->insertGetId([
                'classroom_session_id' => $sessionId,
                'lesson_id' => $lessonId,
                'sequence_order' => DB::table('classroom_session_lessons')->where('classroom_session_id', $sessionId)->max('sequence_order') + 1,
                'status' => 'active',
                'started_at' => $now,
            ]);
        }

        DB::table('classroom_session_activities')->insert([
            'session_lesson_id' => $sessionLessonId,
            'activity_id' => $activityId,
            'sequence_order' => DB::table('classroom_session_activities')->where('session_lesson_id', $sessionLessonId)->max('sequence_order') + 1,
            'assigned_by' => $teacherId,
            'assigned_at' => $now,
            'status' => 'active',
            'started_at' => $now,
        ]);
    }

    // The section's modules with every active lesson and activity, in the shape the Start Class modal reads.
    private function modules(int $sectionId, ?int $schoolYearId): array
    {
        $modules = DB::table('section_modules')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where(['section_modules.section_id' => $sectionId, 'section_modules.is_active' => true, 'modules.is_active' => true])
            ->orderBy('modules.id')
            ->get(['modules.id', 'modules.name']);

        // Only the school year's active quarter is offered here; with no active quarter, nothing is — a stale leftover quarter should never be picked.
        $activeQuarter = $schoolYearId
            ? DB::table('quarters')->where(['school_year_id' => $schoolYearId, 'status' => 'active'])->value('quarter_number')
            : null;

        $lessons = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->whereIn('module_domains.module_id', $modules->pluck('id'))
            ->where('lessons.is_active', true)
            ->where('lessons.quarter_number', $activeQuarter ?? -1)
            ->orderBy('lessons.quarter_number')->orderBy('lessons.week_number')->orderBy('lessons.day_of_week')->orderBy('lessons.sequence_order')
            ->get(['lessons.id', 'module_domains.module_id', 'lessons.title', 'lessons.quarter_number', 'lessons.week_number', 'lessons.day_of_week'])
            ->groupBy('module_id');

        $activities = DB::table('activities')
            ->whereIn('lesson_id', $lessons->flatten()->pluck('id'))
            ->where('is_active', true)
            ->orderBy('sequence_order')
            ->get(['id', 'lesson_id', 'name'])
            ->groupBy('lesson_id');

        $days = $this->usableSchedules($sectionId)
            ->join('schedule_days', 'schedule_days.schedule_id', '=', 'class_schedules.id')
            ->orderBy('schedule_days.day_of_week')
            ->get(['section_modules.module_id', 'schedule_days.day_of_week'])
            ->groupBy('module_id');

        return $modules->map(fn ($m) => [
            'id' => $m->id,
            'moduleName' => $m->name,
            'scheduleDays' => ($days[$m->id] ?? collect())->map(fn ($d) => ['day' => CurriculumController::DAY_NAMES[$d->day_of_week]])->values(),
            'lessons' => ($lessons[$m->id] ?? collect())->map(fn ($l) => [
                'id' => $l->id,
                'lessonName' => $l->title,
                'week' => $l->week_number ?? 0,
                'activities' => ($activities[$l->id] ?? collect())->map(fn ($a) => [
                    'id' => $a->id,
                    'activityName' => $a->name,
                    'day' => $l->day_of_week ? ucfirst(strtolower(CurriculumController::DAY_NAMES[$l->day_of_week])) : '',
                ])->values(),
            ])->values(),
        ])->values()->all();
    }

    // Roster cards: who is playing or done with the activity on screen, and what each learner finished or missed today.
    private function learners(int $sectionId, Collection $enrollments, ?object $active): array
    {
        $enrollmentIds = $enrollments->keys();
        $today = ClassSession::today();

        $current = $active
            ? DB::table('learner_activity_assignments')->where('session_activity_id', $active->session_activity_id)->get()->keyBy('enrollment_id')
            : collect();
        // Learners who already finished the activity on screen, in this class or an earlier one (latest run wins).
        $finished = $active
            ? DB::table('learner_activity_assignments')->where(['activity_id' => $active->activity_id, 'status' => 'completed'])
                ->whereIn('enrollment_id', $enrollmentIds)->orderBy('completed_at')->get()->keyBy('enrollment_id')
            : collect();

        // Today's work in this section, with the activity, its module and week.
        $todaysWork = fn () => DB::table('learner_activity_assignments')
            ->join('activities', 'activities.id', '=', 'learner_activity_assignments.activity_id')
            ->join('lessons', 'lessons.id', '=', 'activities.lesson_id')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->whereIn('learner_activity_assignments.enrollment_id', $enrollmentIds);

        $completed = $todaysWork()
            ->where('learner_activity_assignments.status', 'completed')
            ->whereRaw("(learner_activity_assignments.completed_at AT TIME ZONE 'Asia/Manila')::date = ?", [$today])
            ->orderBy('learner_activity_assignments.completed_at')
            ->get(['learner_activity_assignments.id', 'learner_activity_assignments.enrollment_id', 'activities.id as activity_id', 'activities.name as activity_name', 'modules.name as module_name', 'lessons.week_number']);
        $attempts = DB::table('learner_activity_attempts')->whereIn('assignment_id', $completed->pluck('id')->merge($current->pluck('id'))->merge($finished->pluck('id')))
            ->orderBy('attempt_number')->get()->groupBy('assignment_id')->map->last();

        // Activities shown today in this section's classes where the learner was marked absent.
        $missed = DB::table('classroom_session_learners')
            ->join('classroom_sessions', 'classroom_sessions.id', '=', 'classroom_session_learners.classroom_session_id')
            ->join('section_modules', 'section_modules.id', '=', 'classroom_sessions.section_module_id')
            ->join('classroom_session_lessons', 'classroom_session_lessons.classroom_session_id', '=', 'classroom_sessions.id')
            ->join('classroom_session_activities', 'classroom_session_activities.session_lesson_id', '=', 'classroom_session_lessons.id')
            ->join('activities', 'activities.id', '=', 'classroom_session_activities.activity_id')
            ->join('lessons', 'lessons.id', '=', 'activities.lesson_id')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where('section_modules.section_id', $sectionId)
            ->where('classroom_sessions.session_date', $today)
            ->where('classroom_session_learners.attendance_status', 'absent')
            ->get(['classroom_session_learners.enrollment_id', 'activities.id as activity_id', 'activities.name as activity_name', 'modules.name as module_name', 'lessons.week_number'])
            ->groupBy('enrollment_id');

        $learners = Learner::whereIn('id', $enrollments->values())->with('latestEnrollment.section')
            ->orderBy('last_name')->orderBy('first_name')->get();
        $enrollmentOf = $enrollments->flip();
        $isPractical = $active && $active->activity_format === 'practical';

        return $learners->map(function (Learner $learner) use ($enrollmentOf, $current, $finished, $completed, $attempts, $missed, $active, $isPractical) {
            $enrollmentId = $enrollmentOf[$learner->id];
            $assignment = $finished[$enrollmentId] ?? $current[$enrollmentId] ?? null;
            $doneToday = $completed->where('enrollment_id', $enrollmentId)->keyBy('activity_id');
            $currentAttempt = $assignment ? ($attempts[$assignment->id] ?? null) : null;

            return $learner->toListArray() + [
                'participantStatus' => match ($assignment?->status) {
                    'completed' => 'Finished',
                    'in_progress' => 'Started the Activity',
                    default => null,
                },
                'completedActivities' => $doneToday->map(function ($done) use ($attempts) {
                    $attempt = $attempts[$done->id] ?? null;

                    return array_filter([
                        'moduleName' => $done->module_name,
                        'activityName' => $done->activity_name,
                        'week' => $done->week_number,
                        'score' => $attempt?->score !== null ? (float) $attempt->score : null,
                        'activityScore' => $attempt?->max_score !== null ? (float) $attempt->max_score : null,
                        'practicalResult' => $attempt?->practical_rating,
                    ], fn ($v) => $v !== null);
                })->values(),
                'missingActivities' => ($missed[$enrollmentId] ?? collect())
                    ->reject(fn ($m) => $doneToday->has($m->activity_id))
                    ->unique('activity_id')
                    ->map(fn ($m) => array_filter(['moduleName' => $m->module_name, 'activityName' => $m->activity_name, 'week' => $m->week_number], fn ($v) => $v !== null))
                    ->values(),
                'activePracticalActivity' => $isPractical ? [
                    'activityId' => $active->activity_id,
                    'activityName' => $active->activity_name,
                    'ratingStatus' => $currentAttempt?->practical_rating ?? 'NOT_RATED',
                    'remarks' => $currentAttempt?->remarks,
                ] : null,
            ];
        })->all();
    }
}
