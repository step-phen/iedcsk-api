<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Teachers now rate a practical activity 1 to 5 instead of Needs Help / Good / Very Good.
    // Saved ratings become Needs Help 2, Good 4, Very Good 5, as the school chose.
    public function up(): void
    {
        DB::transaction(function () {
            DB::statement('ALTER TABLE learner_activity_attempts DROP CONSTRAINT learner_activity_attempts_rating_check');
            DB::statement("ALTER TABLE learner_activity_attempts ALTER COLUMN practical_rating TYPE smallint USING (CASE practical_rating WHEN 'needs_help' THEN 2 WHEN 'good' THEN 4 WHEN 'very_good' THEN 5 END)");
            DB::statement('ALTER TABLE learner_activity_attempts ADD CONSTRAINT learner_activity_attempts_rating_check CHECK (practical_rating IS NULL OR practical_rating BETWEEN 1 AND 5)');
        });
    }

    // Back to words: converted ratings round-trip exactly; a new 1 or 3 joins the nearest (2 or 4).
    public function down(): void
    {
        DB::transaction(function () {
            DB::statement('ALTER TABLE learner_activity_attempts DROP CONSTRAINT learner_activity_attempts_rating_check');
            DB::statement("ALTER TABLE learner_activity_attempts ALTER COLUMN practical_rating TYPE varchar(20) USING (CASE WHEN practical_rating = 5 THEN 'very_good' WHEN practical_rating >= 3 THEN 'good' WHEN practical_rating IS NOT NULL THEN 'needs_help' END)");
            DB::statement("ALTER TABLE learner_activity_attempts ADD CONSTRAINT learner_activity_attempts_rating_check CHECK (practical_rating IS NULL OR practical_rating IN ('needs_help', 'good', 'very_good'))");
        });
    }
};
