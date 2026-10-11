<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomRecordRequest;
use App\Models\Teacher;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Class Scores: every activity of one module and quarter as columns, each learner's latest result in the cells.
class ClassroomRecordController extends Controller
{
    public const PRACTICAL_POINTS = ['needs_help' => 1, 'good' => 2, 'very_good' => 3];

    public function show(ClassroomRecordRequest $request, #[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);

        $modules = DB::table('section_modules')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where(['section_modules.section_id' => $section->id, 'section_modules.is_active' => true])
            ->orderBy('modules.id')
            ->get(['modules.id', 'modules.name']);
        // A stale or foreign module falls back to the first one, the same way ?section= does.
        $moduleId = $modules->firstWhere('id', $request->integer('moduleId'))?->id ?? $modules->first()?->id;
        $quarter = $request->integer('quarter') ?: (DB::table('quarters')
            ->where(['school_year_id' => $section->school_year_id, 'status' => 'active'])
            ->value('quarter_number') ?? 1);

        // A module with more than one developmental domain (MAPEH) shows one domain's columns at a time.
        $domains = DB::table('module_domains')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where('module_domains.module_id', $moduleId)
            ->orderBy('module_domains.id')
            ->get(['module_domains.id as id', 'developmental_domains.name as name']);
        $domainId = $domains->firstWhere('id', $request->integer('domainId'))?->id ?? $domains->first()?->id;

        $columns = DB::table('activities')
            ->join('lessons', 'lessons.id', '=', 'activities.lesson_id')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->leftJoin('digital_activity_details', 'digital_activity_details.activity_id', '=', 'activities.id')
            ->where('module_domains.id', $domainId)
            ->where(['lessons.quarter_number' => $quarter, 'lessons.is_active' => true, 'activities.is_active' => true])
            ->orderBy('lessons.week_number')->orderBy('lessons.day_of_week')->orderBy('lessons.sequence_order')->orderBy('activities.sequence_order')
            ->get(['activities.id', 'activities.name', 'activities.activity_format', 'lessons.week_number', 'lessons.day_of_week', 'digital_activity_details.max_score']);

        $learners = DB::table('learner_school_years')
            ->join('learners', 'learners.id', '=', 'learner_school_years.learner_id')
            ->where(['learner_school_years.section_id' => $section->id, 'learner_school_years.status' => 'active'])
            ->orderBy('learners.last_name')->orderBy('learners.first_name')
            ->get(['learner_school_years.id as enrollment_id', 'learners.id', 'learners.first_name', 'learners.middle_name', 'learners.last_name', 'learners.extension_name']);

        $results = self::latestResults($learners->pluck('enrollment_id'), $columns->pluck('id'))->groupBy('enrollment_id');

        return response()->json([
            'modules' => $modules,
            'moduleId' => $moduleId,
            'domains' => $domains,
            'domainId' => $domainId,
            'quarter' => $quarter,
            'columns' => $columns->map(fn ($c) => [
                'activityId' => $c->id,
                'name' => $c->name,
                'format' => $c->activity_format,
                'week' => $c->week_number,
                'day' => $c->day_of_week,
                'maxScore' => $c->max_score !== null ? (float) $c->max_score : null,
            ]),
            'rows' => $learners->map(function ($l) use ($results) {
                $mine = $results->get($l->enrollment_id, collect());
                // Digital scores count as they are; a rated practical counts on a 3-point scale, an unrated one not yet.
                $counted = $mine->map(fn ($r) => $r->max_score !== null
                    ? [(float) $r->score, (float) $r->max_score]
                    : ($r->practical_rating ? [(float) self::PRACTICAL_POINTS[$r->practical_rating], 3.0] : null))->filter();

                return [
                    'learnerId' => $l->id,
                    'firstName' => $l->first_name,
                    'middleName' => $l->middle_name,
                    'lastName' => $l->last_name,
                    'extensionName' => $l->extension_name,
                    'cells' => (object) $mine->mapWithKeys(fn ($r) => [$r->activity_id => self::cell($r)])->all(),
                    'total' => $counted->isEmpty() ? null : ['score' => $counted->sum(0), 'maxScore' => $counted->sum(1)],
                ];
            }),
        ]);
    }

    // Newest finished attempt first, so the first one kept per learner and activity is the latest result.
    public static function latestResults(Collection $enrollmentIds, Collection $activityIds): Collection
    {
        return DB::table('learner_activity_attempts')
            ->join('learner_activity_assignments', 'learner_activity_assignments.id', '=', 'learner_activity_attempts.assignment_id')
            ->whereIn('learner_activity_assignments.enrollment_id', $enrollmentIds)
            ->whereIn('learner_activity_assignments.activity_id', $activityIds)
            ->whereIn('learner_activity_attempts.status', ['evaluated', 'submitted'])
            ->orderByRaw('coalesce(learner_activity_attempts.evaluated_at, learner_activity_attempts.submitted_at) desc')
            ->get(['learner_activity_assignments.enrollment_id', 'learner_activity_assignments.activity_id', 'learner_activity_attempts.score', 'learner_activity_attempts.max_score', 'learner_activity_attempts.practical_rating'])
            ->unique(fn ($r) => $r->enrollment_id.'-'.$r->activity_id)
            ->values();
    }

    // Digital: score out of its graded items; practical: the teacher's rating, null until rated.
    public static function cell(object $result): array
    {
        return $result->max_score !== null
            ? ['score' => (float) $result->score, 'maxScore' => (float) $result->max_score]
            : ['rating' => $result->practical_rating];
    }
}
