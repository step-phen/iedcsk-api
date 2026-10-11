<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->index()->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('grade_level', 50);
            $table->boolean('is_active')->default(true);

            $table->unique(['school_year_id', 'grade_level', 'name']);
            // Target for the enrollment composite key that pins a section to its school year.
            $table->unique(['id', 'school_year_id']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
