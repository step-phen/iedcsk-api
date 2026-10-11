<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarters', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->smallInteger('quarter_number');
            $table->string('name', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20);

            $table->unique(['school_year_id', 'quarter_number']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE quarters ADD CONSTRAINT quarters_number_check CHECK (quarter_number BETWEEN 1 AND 4)');
        DB::statement('ALTER TABLE quarters ADD CONSTRAINT quarters_dates_check CHECK (start_date <= end_date)');
        DB::statement("ALTER TABLE quarters ADD CONSTRAINT quarters_status_check CHECK (status IN ('upcoming', 'active', 'completed'))");

    }

    public function down(): void
    {
        Schema::dropIfExists('quarters');
    }
};
