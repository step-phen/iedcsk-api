<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // A teacher runs one class at a time, and a class lesson has one activity on screen at a time.
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX classroom_sessions_one_open_per_teacher ON classroom_sessions (teacher_id) WHERE status IN ('scheduled', 'ongoing')");
        DB::statement("CREATE UNIQUE INDEX classroom_session_activities_one_active ON classroom_session_activities (session_lesson_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS classroom_session_activities_one_active');
        DB::statement('DROP INDEX IF EXISTS classroom_sessions_one_open_per_teacher');
    }
};
