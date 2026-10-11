<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_session_learners', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('classroom_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->index()->constrained('learner_school_years')->restrictOnDelete();
            $table->string('attendance_status', 20);
            $table->string('participation_status', 20);
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('attendance_recorded_by')->nullable()->index()->constrained('teachers')->restrictOnDelete();
            $table->timestampTz('attendance_recorded_at')->nullable();

            $table->unique(['classroom_session_id', 'enrollment_id']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE classroom_session_learners ADD CONSTRAINT classroom_session_learners_attendance_check CHECK (attendance_status IN ('unmarked', 'present', 'absent', 'late', 'excused'))");
        DB::statement("ALTER TABLE classroom_session_learners ADD CONSTRAINT classroom_session_learners_participation_check CHECK (participation_status IN ('not_joined', 'joined', 'in_progress', 'completed'))");
        DB::statement("ALTER TABLE classroom_session_learners ADD CONSTRAINT classroom_session_learners_recorded_check CHECK (attendance_status = 'unmarked' OR (attendance_recorded_by IS NOT NULL AND attendance_recorded_at IS NOT NULL))");

    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_session_learners');
    }
};
