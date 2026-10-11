<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarterly_progress_report_details', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('report_id')->constrained('quarterly_progress_reports')->restrictOnDelete();
            $table->foreignId('module_domain_id')->index()->constrained()->restrictOnDelete();
            $table->text('development_observed');
            $table->text('comments_remarks')->nullable();

            $table->unique(['report_id', 'module_domain_id']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarterly_progress_report_details');
    }
};
