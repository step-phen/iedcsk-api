<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_developmental_assessments', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('enrollment_id')->constrained('learner_school_years')->restrictOnDelete();
            $table->foreignId('quarter_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('domain_id')->index()->constrained('developmental_domains')->restrictOnDelete();
            $table->foreignId('teacher_id')->index()->constrained()->restrictOnDelete();
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestampTz('assessed_at');

            $table->unique(['enrollment_id', 'quarter_id', 'domain_id']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_developmental_assessments');
    }
};
