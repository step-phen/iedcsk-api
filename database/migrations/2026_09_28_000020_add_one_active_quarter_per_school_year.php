<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The dashboard reads "the" active quarter of a school year, so each year
    // has at most one; upcoming and completed quarters are unlimited.
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX quarters_one_active_per_school_year ON quarters (school_year_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS quarters_one_active_per_school_year');
    }
};
