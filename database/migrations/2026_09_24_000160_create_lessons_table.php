<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('module_domain_id')->index()->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->text('lesson_video_url')->nullable();
            $table->smallInteger('quarter_number')->nullable();
            $table->smallInteger('week_number')->nullable();
            $table->smallInteger('day_of_week')->nullable();
            $table->integer('sequence_order');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE lessons ADD CONSTRAINT lessons_sequence_check CHECK (sequence_order > 0)');
        DB::statement('ALTER TABLE lessons ADD CONSTRAINT lessons_quarter_check CHECK (quarter_number IS NULL OR quarter_number BETWEEN 1 AND 4)');
        DB::statement('ALTER TABLE lessons ADD CONSTRAINT lessons_week_check CHECK (week_number IS NULL OR (week_number > 0 AND quarter_number IS NOT NULL))');
        DB::statement('ALTER TABLE lessons ADD CONSTRAINT lessons_day_check CHECK (day_of_week IS NULL OR day_of_week BETWEEN 1 AND 7)');

    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
