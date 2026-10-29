<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Queries shared by the teacher's classroom and the learner devices for one classroom_sessions row.
class ClassSession
{
    public static function today(): string
    {
        return Carbon::now('Asia/Manila')->toDateString();
    }

    // The one lock attendance saves and learner joins both take, so a mark and a join for the same learner never interleave.
    // Ordered by id so a multi-learner save can't deadlock against another.
    public static function lockEnrollments(iterable $enrollmentIds): void
    {
        DB::table('learner_school_years')->whereIn('id', collect($enrollmentIds)->all())->orderBy('id')->lockForUpdate()->pluck('id');
    }

    // lockEnrollments, then whether every one of them is still active. A write that checked "active" before its
    // transaction re-checks here, so a withdrawal that committed in between refuses it instead of slipping past.
    public static function lockActive(iterable $enrollmentIds): bool
    {
        $ids = collect($enrollmentIds)->unique()->values();
        self::lockEnrollments($ids);

        return DB::table('learner_school_years')->whereIn('id', $ids->all())->where('status', 'active')->count() === $ids->count();
    }

    // Enrollments that started an activity today (Manila date) in any of this section's classes, any lesson.
    public static function startedToday(int $sectionId, iterable $enrollmentIds): Collection
    {
        return DB::table('learner_activity_assignments')
            ->join('classroom_session_activities', 'classroom_session_activities.id', '=', 'learner_activity_assignments.session_activity_id')
            ->join('classroom_session_lessons', 'classroom_session_lessons.id', '=', 'classroom_session_activities.session_lesson_id')
            ->join('classroom_sessions', 'classroom_sessions.id', '=', 'classroom_session_lessons.classroom_session_id')
            ->join('section_modules', 'section_modules.id', '=', 'classroom_sessions.section_module_id')
            ->where('section_modules.section_id', $sectionId)
            ->where('classroom_sessions.session_date', self::today())
            ->whereIn('learner_activity_assignments.status', ['in_progress', 'completed'])
            ->whereIn('learner_activity_assignments.enrollment_id', collect($enrollmentIds)->all())
            ->distinct()
            ->pluck('learner_activity_assignments.enrollment_id');
    }

    // The activity on screen in a class: its session activity row, the activity, its lesson and module, or null.
    public static function activeActivity(int $sessionId): ?object
    {
        return DB::table('classroom_session_activities')
            ->join('classroom_session_lessons', 'classroom_session_lessons.id', '=', 'classroom_session_activities.session_lesson_id')
            ->join('activities', 'activities.id', '=', 'classroom_session_activities.activity_id')
            ->join('lessons', 'lessons.id', '=', 'activities.lesson_id')
            ->leftJoin('digital_activity_details', 'digital_activity_details.activity_id', '=', 'activities.id')
            ->leftJoin('practical_activity_details', 'practical_activity_details.activity_id', '=', 'activities.id')
            ->where('classroom_session_lessons.classroom_session_id', $sessionId)
            ->where('classroom_session_activities.status', 'active')
            ->first([
                'classroom_session_activities.id as session_activity_id',
                'activities.id as activity_id',
                'activities.name as activity_name',
                'activities.activity_code',
                'activities.activity_format',
                'activities.instructions as activity_instructions',
                'digital_activity_details.game_type',
                'practical_activity_details.demo_video_url',
                'lessons.id as lesson_id',
                'lessons.title as lesson_title',
                'lessons.lesson_video_url',
            ]);
    }

    // Closes a class: the session, its open lesson and activity, the roster, and any unfinished attempts.
    public static function end(int $sessionId): void
    {
        $now = now();
        $lessonIds = DB::table('classroom_session_lessons')->where('classroom_session_id', $sessionId)->pluck('id');
        $sessionActivityIds = DB::table('classroom_session_activities')->whereIn('session_lesson_id', $lessonIds)->pluck('id');

        DB::table('classroom_session_activities')->whereIn('id', $sessionActivityIds)->where('status', 'active')
            ->update(['status' => 'completed', 'ended_at' => $now, 'updated_at' => $now]);
        DB::table('classroom_session_lessons')->whereIn('id', $lessonIds)->where('status', 'active')
            ->update(['status' => 'completed', 'ended_at' => $now, 'updated_at' => $now]);
        DB::table('learner_activity_attempts')
            ->whereIn('assignment_id', DB::table('learner_activity_assignments')->whereIn('session_activity_id', $sessionActivityIds)->select('id'))
            ->where('status', 'in_progress')
            ->update(['status' => 'abandoned', 'updated_at' => $now]);
        DB::table('classroom_session_learners')->where('classroom_session_id', $sessionId)->whereIn('participation_status', ['joined', 'in_progress'])
            ->update(['participation_status' => 'completed', 'completed_at' => $now, 'updated_at' => $now]);
        DB::table('classroom_sessions')->where('id', $sessionId)
            ->update(['status' => 'completed', 'ended_at' => $now, 'updated_at' => $now]);
    }

    // Public URL for a file on the public disk, as the Modules board builds it.
    public static function fileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, '/') || str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }

    // A practical activity opens to learners only once its demo is an uploaded file that is really on the public disk:
    // a saved path whose file went missing, or a link the API can't check, keeps it locked.
    public static function demoVideoReady(?object $activity): bool
    {
        $path = $activity?->demo_video_url;

        return $activity?->activity_format === 'practical' && $path
            && ! str_starts_with($path, '/') && ! str_starts_with($path, 'http')
            && Storage::disk('public')->exists($path);
    }

    // The practical activity on screen has no usable demo, so learners can't start or submit it.
    public static function practicalLocked(?object $activity): bool
    {
        return $activity?->activity_format === 'practical' && ! self::demoVideoReady($activity);
    }
}
