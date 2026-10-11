<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_activity_assignments', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('enrollment_id')->index()->constrained('learner_school_years')->restrictOnDelete();
            $table->foreignId('activity_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('quarter_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('session_activity_id')->nullable()->index()->constrained('classroom_session_activities')->restrictOnDelete();
            $table->timestampTz('assigned_at');
            $table->timestampTz('due_at')->nullable();
            $table->string('status', 20);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('CREATE UNIQUE INDEX learner_activity_assignments_session_unique ON learner_activity_assignments (enrollment_id, session_activity_id) WHERE session_activity_id IS NOT NULL');
        DB::statement("ALTER TABLE learner_activity_assignments ADD CONSTRAINT learner_activity_assignments_status_check CHECK (status IN ('assigned', 'in_progress', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE learner_activity_assignments ADD CONSTRAINT learner_activity_assignments_completed_check CHECK ((status = 'completed') = (completed_at IS NOT NULL))");

    }

    public function down(): void
    {
        Schema::dropIfExists('learner_activity_assignments');
    }
};
