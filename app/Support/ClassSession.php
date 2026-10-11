<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Queries shared by the teacher's classroom and the learner devices for one classroom_sessions row.
class ClassSession
{
    public static function today(): string
    {
        return Carbon::now('Asia/Manila')->toDateString();
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
}
