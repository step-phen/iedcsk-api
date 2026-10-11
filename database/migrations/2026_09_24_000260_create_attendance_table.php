<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('enrollment_id')->constrained('learner_school_years')->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20);
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->index()->constrained('teachers')->restrictOnDelete();
            $table->timestampTz('recorded_at');

            $table->unique(['enrollment_id', 'attendance_date']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE attendance ADD CONSTRAINT attendance_status_check CHECK (status IN ('present', 'absent', 'late', 'excused'))");

    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
