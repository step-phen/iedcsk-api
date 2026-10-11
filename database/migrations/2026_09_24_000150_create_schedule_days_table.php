<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('schedule_id')->constrained('class_schedules')->restrictOnDelete();
            $table->smallInteger('day_of_week');

            $table->unique(['schedule_id', 'day_of_week']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE schedule_days ADD CONSTRAINT schedule_days_day_check CHECK (day_of_week BETWEEN 1 AND 7)');

    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_days');
    }
};
