<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('section_module_id')->index()->constrained()->restrictOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->date('effective_from');
            $table->date('effective_until');
            $table->string('timezone', 50)->default('Asia/Manila');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE class_schedules ADD CONSTRAINT class_schedules_times_check CHECK (start_time < end_time)');
        DB::statement('ALTER TABLE class_schedules ADD CONSTRAINT class_schedules_dates_check CHECK (effective_from <= effective_until)');

    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
