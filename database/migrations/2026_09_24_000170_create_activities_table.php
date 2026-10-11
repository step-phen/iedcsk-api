<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('lesson_id')->index()->constrained()->restrictOnDelete();
            $table->string('activity_code', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->text('instructions');
            $table->string('activity_format', 20);
            $table->integer('sequence_order');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_format_check CHECK (activity_format IN ('digital', 'practical'))");
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_sequence_check CHECK (sequence_order > 0)');

    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
