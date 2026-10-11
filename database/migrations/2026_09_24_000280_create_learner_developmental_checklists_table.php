<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_developmental_checklists', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('enrollment_id')->constrained('learner_school_years')->restrictOnDelete();
            $table->foreignId('checklist_item_id')->index()->constrained('developmental_checklist_items')->restrictOnDelete();
            $table->foreignId('quarter_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->index()->constrained()->restrictOnDelete();
            $table->string('rating', 30);
            $table->text('remarks')->nullable();
            $table->timestampTz('assessed_at');

            $table->unique(['enrollment_id', 'checklist_item_id', 'quarter_id']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE learner_developmental_checklists ADD CONSTRAINT learner_developmental_checklists_rating_check CHECK (rating IN ('achieved', 'developing', 'needs_support', 'not_yet_observed'))");

    }

    public function down(): void
    {
        Schema::dropIfExists('learner_developmental_checklists');
    }
};
