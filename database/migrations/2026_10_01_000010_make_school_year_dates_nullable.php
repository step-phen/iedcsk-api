<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // An upcoming school year may be created before its official dates are known; it has
    // both dates or neither, and only upcoming years may be undated (activation needs dates).
    public function up(): void
    {
        DB::statement('ALTER TABLE school_years ALTER COLUMN start_date DROP NOT NULL');
        DB::statement('ALTER TABLE school_years ALTER COLUMN end_date DROP NOT NULL');
        DB::statement('ALTER TABLE school_years ADD CONSTRAINT school_years_dates_both_or_neither CHECK ((start_date IS NULL) = (end_date IS NULL))');
        DB::statement("ALTER TABLE school_years ADD CONSTRAINT school_years_dated_unless_upcoming CHECK (status = 'upcoming' OR start_date IS NOT NULL)");
    }

    // Fails while undated school years exist; give them dates first.
    public function down(): void
    {
        DB::statement('ALTER TABLE school_years DROP CONSTRAINT IF EXISTS school_years_dated_unless_upcoming');
        DB::statement('ALTER TABLE school_years DROP CONSTRAINT IF EXISTS school_years_dates_both_or_neither');
        DB::statement('ALTER TABLE school_years ALTER COLUMN start_date SET NOT NULL');
        DB::statement('ALTER TABLE school_years ALTER COLUMN end_date SET NOT NULL');
    }
};
