<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_activity_attempts', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('assignment_id')->constrained('learner_activity_assignments')->restrictOnDelete();
            $table->integer('attempt_number');
            $table->string('status', 20);
            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('max_score', 10, 2)->nullable();
            $table->string('practical_rating', 20)->nullable();
            $table->foreignId('evaluated_by')->nullable()->index()->constrained('teachers')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('evaluated_at')->nullable();
            $table->timestampTz('demo_watched_at')->nullable();

            $table->unique(['assignment_id', 'attempt_number']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE learner_activity_attempts ADD CONSTRAINT learner_activity_attempts_attempt_number_check CHECK (attempt_number > 0)');
        DB::statement("ALTER TABLE learner_activity_attempts ADD CONSTRAINT learner_activity_attempts_status_check CHECK (status IN ('in_progress', 'submitted', 'evaluated', 'abandoned'))");
        DB::statement("ALTER TABLE learner_activity_attempts ADD CONSTRAINT learner_activity_attempts_rating_check CHECK (practical_rating IS NULL OR practical_rating IN ('needs_help', 'good', 'very_good'))");
        DB::statement('ALTER TABLE learner_activity_attempts ADD CONSTRAINT learner_activity_attempts_result_exclusive_check CHECK (practical_rating IS NULL OR (score IS NULL AND max_score IS NULL))');
        DB::statement("ALTER TABLE learner_activity_attempts ADD CONSTRAINT learner_activity_attempts_evaluated_check CHECK (status <> 'evaluated' OR (score IS NOT NULL AND max_score > 0 AND score BETWEEN 0 AND max_score AND practical_rating IS NULL) OR (practical_rating IS NOT NULL AND evaluated_by IS NOT NULL AND score IS NULL AND max_score IS NULL))");

    }

    public function down(): void
    {
        Schema::dropIfExists('learner_activity_attempts');
    }
};
