<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_session_lessons', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('classroom_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->index()->constrained()->restrictOnDelete();
            $table->integer('sequence_order');
            $table->string('status', 20);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();

            $table->unique(['classroom_session_id', 'sequence_order']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("CREATE UNIQUE INDEX classroom_session_lessons_one_active ON classroom_session_lessons (classroom_session_id) WHERE status = 'active'");
        DB::statement("ALTER TABLE classroom_session_lessons ADD CONSTRAINT classroom_session_lessons_status_check CHECK (status IN ('planned', 'active', 'completed', 'skipped'))");
        DB::statement('ALTER TABLE classroom_session_lessons ADD CONSTRAINT classroom_session_lessons_ended_check CHECK (ended_at IS NULL OR (started_at IS NOT NULL AND ended_at >= started_at))');

    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_session_lessons');
    }
};
