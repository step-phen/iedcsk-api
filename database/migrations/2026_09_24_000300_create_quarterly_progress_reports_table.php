<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarterly_progress_reports', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('enrollment_id')->constrained('learner_school_years')->restrictOnDelete();
            $table->foreignId('quarter_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->index()->constrained()->restrictOnDelete();
            $table->text('overall_remarks')->nullable();
            $table->string('status', 20);
            $table->timestampTz('generated_at');
            $table->timestampTz('finalized_at')->nullable();

            $table->unique(['enrollment_id', 'quarter_id']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE quarterly_progress_reports ADD CONSTRAINT quarterly_progress_reports_status_check CHECK (status IN ('draft', 'finalized'))");
        DB::statement("ALTER TABLE quarterly_progress_reports ADD CONSTRAINT quarterly_progress_reports_finalized_check CHECK ((status = 'finalized') = (finalized_at IS NOT NULL))");

    }

    public function down(): void
    {
        Schema::dropIfExists('quarterly_progress_reports');
    }
};
