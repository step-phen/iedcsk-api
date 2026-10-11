<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Kindergarten activities have no pass or fail; progress is read from score and max_score.
    public function up(): void
    {
        DB::statement('ALTER TABLE digital_activity_details DROP COLUMN passing_score');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE digital_activity_details ADD COLUMN passing_score numeric(10, 2) NULL');
        DB::statement('ALTER TABLE digital_activity_details ADD CONSTRAINT digital_activity_details_passing_score_check CHECK (passing_score IS NULL OR passing_score BETWEEN 0 AND max_score)');
    }
};
