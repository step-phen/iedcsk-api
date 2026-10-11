<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A digital activity's questions and answer key; the server grades submitted answers against correct_answer.
    public function up(): void
    {
        Schema::create('activity_questions', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('question_key', 100);
            $table->integer('question_order');
            $table->jsonb('question_data');
            $table->jsonb('correct_answer');

            $table->unique(['activity_id', 'question_key']);
            $table->unique(['activity_id', 'question_order']);
        });
        DB::statement('ALTER TABLE activity_questions ADD CONSTRAINT activity_questions_order_check CHECK (question_order > 0)');
        DB::statement("ALTER TABLE activity_questions ADD CONSTRAINT activity_questions_key_check CHECK (btrim(question_key) <> '')");
        DB::statement("ALTER TABLE activity_questions ADD CONSTRAINT activity_questions_data_check CHECK (jsonb_typeof(question_data) = 'object')");
        DB::statement("ALTER TABLE activity_questions ADD CONSTRAINT activity_questions_answer_check CHECK (jsonb_typeof(correct_answer) = 'object' AND jsonb_typeof(correct_answer->'answer') = 'string')");
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_questions');
    }
};
