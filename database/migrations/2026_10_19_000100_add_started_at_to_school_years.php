<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // When teaching began in a school year (SchoolYear::markStarted). Set once and never cleared, unlike quarter
    // statuses, which a quarter or year switch can put back to upcoming. New modules are only added before it is set.
    public function up(): void
    {
        DB::statement('ALTER TABLE school_years ADD COLUMN started_at timestamptz');
        $this->backfill();
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE school_years DROP COLUMN started_at');
    }

    // A year already started if one of its quarters was ever activated (active or completed now) or it has any
    // classroom record. Stamped with its earliest record time, else the migration time. Only started_at is written.
    public function backfill(): void
    {
        DB::statement(<<<'SQL'
            UPDATE school_years SET started_at = COALESCE(evidence.first_record, now())
            FROM (
                SELECT y.id,
                    LEAST(
                        (SELECT min(cs.started_at) FROM classroom_sessions cs JOIN quarters q ON q.id = cs.quarter_id WHERE q.school_year_id = y.id),
                        (SELECT min(a.assigned_at) FROM learner_activity_assignments a JOIN quarters q ON q.id = a.quarter_id WHERE q.school_year_id = y.id),
                        (SELECT min(r.assessed_at) FROM learner_developmental_checklists r JOIN quarters q ON q.id = r.quarter_id WHERE q.school_year_id = y.id),
                        (SELECT min(p.generated_at) FROM quarterly_progress_reports p JOIN quarters q ON q.id = p.quarter_id WHERE q.school_year_id = y.id),
                        (SELECT min(t.recorded_at) FROM attendance t JOIN learner_school_years e ON e.id = t.enrollment_id WHERE e.school_year_id = y.id)
                    ) AS first_record
                FROM school_years y
            ) AS evidence
            WHERE school_years.id = evidence.id
              AND school_years.started_at IS NULL
              AND (
                  evidence.first_record IS NOT NULL
                  OR EXISTS (SELECT 1 FROM quarters q WHERE q.school_year_id = school_years.id AND q.status IN ('active', 'completed'))
                  OR EXISTS (SELECT 1 FROM classroom_sessions cs JOIN quarters q ON q.id = cs.quarter_id WHERE q.school_year_id = school_years.id)
              )
            SQL);
    }
};
