<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_school_years', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('learner_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->index();
            $table->date('enrolled_on');
            $table->string('status', 20);

            $table->unique(['learner_id', 'school_year_id']);
            // The section must belong to the same school year as the enrollment.
            $table->foreign(['section_id', 'school_year_id'])->references(['id', 'school_year_id'])->on('sections')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE learner_school_years ADD CONSTRAINT learner_school_years_status_check CHECK (status IN ('active', 'completed', 'transferred', 'withdrawn'))");

    }

    public function down(): void
    {
        Schema::dropIfExists('learner_school_years');
    }
};
