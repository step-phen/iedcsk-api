<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_sessions', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('section_module_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('schedule_id')->nullable()->index()->constrained('class_schedules')->restrictOnDelete();
            $table->foreignId('quarter_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->index()->constrained()->restrictOnDelete();
            $table->date('session_date');
            $table->time('planned_start_time');
            $table->time('planned_end_time');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE classroom_sessions ADD CONSTRAINT classroom_sessions_planned_times_check CHECK (planned_start_time < planned_end_time)');
        DB::statement('ALTER TABLE classroom_sessions ADD CONSTRAINT classroom_sessions_ended_check CHECK (ended_at IS NULL OR (started_at IS NOT NULL AND ended_at >= started_at))');
        DB::statement("ALTER TABLE classroom_sessions ADD CONSTRAINT classroom_sessions_status_check CHECK (status IN ('scheduled', 'ongoing', 'completed', 'cancelled'))");

    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_sessions');
    }
};
