<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_session_activities', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('session_lesson_id')->constrained('classroom_session_lessons')->restrictOnDelete();
            $table->foreignId('activity_id')->index()->constrained()->restrictOnDelete();
            $table->integer('sequence_order');
            $table->foreignId('assigned_by')->index()->constrained('teachers')->restrictOnDelete();
            $table->timestampTz('assigned_at');
            $table->string('status', 20);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();

            $table->unique(['session_lesson_id', 'sequence_order']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE classroom_session_activities ADD CONSTRAINT classroom_session_activities_status_check CHECK (status IN ('planned', 'active', 'completed', 'skipped'))");
        DB::statement('ALTER TABLE classroom_session_activities ADD CONSTRAINT classroom_session_activities_ended_check CHECK (ended_at IS NULL OR (started_at IS NOT NULL AND ended_at >= started_at))');

    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_session_activities');
    }
};
