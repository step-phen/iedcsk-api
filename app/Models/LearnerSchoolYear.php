<?php

namespace App\Models;

use App\Http\Controllers\CurriculumController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class LearnerSchoolYear extends Model
{
    protected $fillable = ['school_year_id', 'section_id', 'enrolled_on', 'status'];

    protected function casts(): array
    {
        return ['enrolled_on' => 'date'];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    // Ready for Grade 1: every active checklist item from Q1 to Q4 is rated achieved or developing
    // in the quarters of the enrollment's own school year, so the same rule reads a past year too.
    public static function whereReadyForGrade1(Builder $enrollments): Builder
    {
        $items = DB::table('developmental_checklist_items')->where(['grade_level' => CurriculumController::GRADE_LEVEL, 'is_active' => true]);
        $total = (clone $items)->count();
        // Until the checklist covers all four quarters nobody can have finished the year, so the list stays empty.
        if ((clone $items)->distinct()->count('quarter_number') < 4) {
            return $enrollments->whereRaw('false');
        }

        return $enrollments
            ->whereIn('learner_school_years.status', ['active', 'completed'])
            ->whereRaw("(
                SELECT count(*) FROM learner_developmental_checklists c
                JOIN developmental_checklist_items i ON i.id = c.checklist_item_id
                JOIN quarters q ON q.id = c.quarter_id
                WHERE c.enrollment_id = learner_school_years.id
                  AND q.school_year_id = learner_school_years.school_year_id
                  AND q.quarter_number = i.quarter_number
                  AND i.grade_level = ? AND i.is_active
                  AND c.rating IN ('achieved', 'developing')
            ) = ?", [CurriculumController::GRADE_LEVEL, $total]);
    }
}
