<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Which fixed section (Joy or Faith) a row is, stored apart from its display name so a renamed section keeps its
    // identity and session. Existing rows are matched by name only when the match is unambiguous; others stay null.
    public function up(): void
    {
        DB::statement('ALTER TABLE sections ADD COLUMN choice varchar(10)');
        DB::statement("ALTER TABLE sections ADD CONSTRAINT sections_choice_check CHECK (choice IS NULL OR choice IN ('Joy', 'Faith'))");
        $this->backfill();
        DB::statement('CREATE UNIQUE INDEX sections_one_choice_per_year ON sections (school_year_id, grade_level, choice) WHERE choice IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sections_one_choice_per_year');
        DB::statement('ALTER TABLE sections DROP COLUMN choice');
    }

    // A section named joy/faith (any case or spacing) gets that choice only if it is the only such name in its year and grade.
    public function backfill(): void
    {
        DB::statement(<<<'SQL'
            UPDATE sections SET choice = matched.choice
            FROM (
                SELECT min(id) AS id, initcap(lower(trim(name))) AS choice
                FROM sections
                WHERE choice IS NULL AND lower(trim(name)) IN ('joy', 'faith')
                GROUP BY school_year_id, grade_level, lower(trim(name))
                HAVING count(*) = 1
            ) AS matched
            WHERE sections.id = matched.id
              AND NOT EXISTS (
                  SELECT 1 FROM sections taken
                  WHERE taken.school_year_id = sections.school_year_id AND taken.grade_level = sections.grade_level AND taken.choice = matched.choice
              )
            SQL);
    }
};
