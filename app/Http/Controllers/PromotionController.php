<?php

namespace App\Http\Controllers;

use App\Models\LearnerSchoolYear;
use App\Models\SchoolYear;
use App\Models\Teacher;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

// Read-only: nothing is stored as a promotion. A learner counts as promoted when their enrollment in a
// completed school year meets the Ready for Grade 1 rule for that year (LearnerSchoolYear::whereReadyForGrade1).
// Each teacher sees only the years and learners of sections they hold.
class PromotionController extends Controller
{
    public function index(#[CurrentUser] Teacher $teacher): JsonResponse
    {
        $years = $this->ownedYears($teacher)->orderByDesc('name')->get();

        return response()->json($years->map(fn (SchoolYear $year) => [
            ...$this->summary($year, $teacher),
            'promotedCount' => $this->promoted($year, $teacher)->count(),
        ]));
    }

    // A year that isn't completed, or where this teacher held no section, reads as not found.
    public function show(#[CurrentUser] Teacher $teacher, int $schoolYear): JsonResponse
    {
        $year = $this->ownedYears($teacher)->findOrFail($schoolYear);

        $learners = $this->promoted($year, $teacher)
            ->join('learners', 'learners.id', '=', 'learner_school_years.learner_id')
            ->orderBy('learners.last_name')->orderBy('learners.first_name')
            ->select('learner_school_years.*')
            ->with(['learner', 'section'])
            ->get();

        return response()->json([
            ...$this->summary($year, $teacher),
            'learners' => $learners->map(fn (LearnerSchoolYear $e) => [
                'learnerId' => $e->learner->id,
                'lrn' => $e->learner->lrn,
                'firstName' => $e->learner->first_name,
                'middleName' => $e->learner->middle_name,
                'lastName' => $e->learner->last_name,
                'extensionName' => $e->learner->extension_name,
                'gender' => $e->learner->gender,
                'birthdate' => $e->learner->birth_date->toDateString(),
                // The section of that year's enrollment, not the learner's latest one.
                'section' => $e->section->name,
            ]),
        ]);
    }

    private function ownedYears(Teacher $teacher): Builder
    {
        return SchoolYear::where('status', 'completed')
            ->whereHas('sections', fn (Builder $s) => $s->where('teacher_id', $teacher->id));
    }

    private function promoted(SchoolYear $year, Teacher $teacher): Builder
    {
        return LearnerSchoolYear::whereReadyForGrade1(LearnerSchoolYear::query()
            ->where('learner_school_years.school_year_id', $year->id)
            ->whereHas('section', fn (Builder $s) => $s->where('teacher_id', $teacher->id)));
    }

    // No promotion date is stored, so the year's end date (null when never set) stands in, labelled as such on the page.
    private function summary(SchoolYear $year, Teacher $teacher): array
    {
        return [
            'schoolYearId' => $year->id,
            'name' => $year->name,
            'endDate' => $year->end_date?->toDateString(),
            // Only this teacher's sections are counted, so they are the adviser.
            'adviser' => $teacher->name,
        ];
    }
}
