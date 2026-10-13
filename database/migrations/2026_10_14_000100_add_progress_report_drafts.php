<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A row keeps the machine draft next to the teacher's text, so edits are visible and survive a regenerate;
    // every finalize freezes the whole report into a numbered version that later corrections never touch.
    public function up(): void
    {
        Schema::table('quarterly_progress_report_details', function (Blueprint $table) {
            $table->text('generated_observed')->nullable();
            $table->jsonb('evidence')->default('[]');
        });

        Schema::table('quarterly_progress_reports', function (Blueprint $table) {
            $table->text('correction_reason')->nullable();
        });

        Schema::create('quarterly_progress_report_versions', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('report_id')->constrained('quarterly_progress_reports')->restrictOnDelete();
            $table->smallInteger('version_number');
            $table->jsonb('snapshot');
            $table->foreignId('finalized_by')->index()->constrained('teachers')->restrictOnDelete();
            $table->timestampTz('finalized_at');
            $table->text('correction_reason')->nullable();

            $table->unique(['report_id', 'version_number']);
        });
        DB::statement('ALTER TABLE quarterly_progress_report_versions ADD CONSTRAINT quarterly_progress_report_versions_number_check CHECK (version_number > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('quarterly_progress_report_versions');

        Schema::table('quarterly_progress_reports', function (Blueprint $table) {
            $table->dropColumn('correction_reason');
        });

        Schema::table('quarterly_progress_report_details', function (Blueprint $table) {
            $table->dropColumn(['generated_observed', 'evidence']);
        });
    }
};
