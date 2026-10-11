<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->string('name', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('upcoming');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        // At most one active school year; the status itself is the flag, so no is_active column.
        DB::statement("CREATE UNIQUE INDEX school_years_one_active ON school_years (status) WHERE status = 'active'");
        DB::statement('ALTER TABLE school_years ADD CONSTRAINT school_years_dates_check CHECK (start_date <= end_date)');
        DB::statement("ALTER TABLE school_years ADD CONSTRAINT school_years_status_check CHECK (status IN ('upcoming', 'active', 'completed'))");

    }

    public function down(): void
    {
        Schema::dropIfExists('school_years');
    }
};
