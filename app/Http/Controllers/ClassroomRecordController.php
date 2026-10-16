<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomRecordRequest;
use App\Models\Teacher;
use App\Support\ProgressReportDraft;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
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
                $counted = $mine->map(fn ($r) => self::points($r))->filter();

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

    /**
     * Strengths & Weaknesses, read-only. Per learner: each module row's activity performance for the quarter (the
     * Classroom Record total, with coverage), and the evidence under it: each checklist skill with the teacher's own
     * rating as its only status, its linked game's result beside it, and the activities no skill links to.
     * A linked game from another quarter's lessons is not used for this quarter's skill.
     */
    public function strengths(ClassroomRecordRequest $request, #[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);
        $quarters = DB::table('quarters')->where('school_year_id', $section->school_year_id)->get(['id', 'quarter_number', 'status'])->keyBy('quarter_number');
        $quarter = $request->integer('quarter') ?: ($quarters->firstWhere('status', 'active')?->quarter_number ?? 1);
        $quarterId = $quarters->get($quarter)?->id;

        // The enrollment pins both the section and its school year.
        $learners = DB::table('learner_school_years')
            ->join('learners', 'learners.id', '=', 'learner_school_years.learner_id')
            ->where('learner_school_years.section_id', $section->id)
            ->whereIn('learner_school_years.status', ['active', 'completed'])
            ->orderBy('learners.last_name')->orderBy('learners.first_name')
            ->get(['learner_school_years.id as enrollment_id', 'learners.id', 'learners.first_name', 'learners.middle_name', 'learners.last_name', 'learners.extension_name']);

        $rows = ProgressReportDraft::rows($section->id);
        $byDomain = $rows->groupBy('domain_id');
        $items = DB::table('developmental_checklist_items')
            ->leftJoin('activities', 'activities.id', '=', 'developmental_checklist_items.activity_id')
            ->leftJoin('lessons', 'lessons.id', '=', 'activities.lesson_id')
            ->where([
                'developmental_checklist_items.grade_level' => $section->grade_level,
                'developmental_checklist_items.is_active' => true,
                'developmental_checklist_items.quarter_number' => $quarter,
            ])
            ->orderBy('developmental_checklist_items.display_order')->orderBy('developmental_checklist_items.id')
            ->get(['developmental_checklist_items.id', 'developmental_checklist_items.item', 'developmental_checklist_items.domain_id', 'developmental_checklist_items.activity_id', 'activities.name as activity_name', 'activities.activity_format', 'lessons.module_domain_id', 'lessons.quarter_number as activity_quarter']);
        // Only a game from this quarter's lessons can speak for this quarter's skill; a replay in a later class still can.
        $linked = $items->filter(fn ($i) => $i->activity_id !== null && (int) $i->activity_quarter === $quarter);
        $linkedIds = $linked->pluck('id')->flip();

        // Every activity of the quarter counts toward its module's coverage, the same columns the Classroom Record shows.
        $activities = DB::table('activities')
            ->join('lessons', 'lessons.id', '=', 'activities.lesson_id')
            ->whereIn('lessons.module_domain_id', $rows->pluck('module_domain_id'))
            ->where(['lessons.quarter_number' => $quarter, 'lessons.is_active' => true, 'activities.is_active' => true])
            ->orderBy('lessons.week_number')->orderBy('lessons.day_of_week')->orderBy('lessons.sequence_order')->orderBy('activities.sequence_order')
            ->get(['activities.id', 'activities.name', 'activities.activity_format', 'lessons.module_domain_id']);
        $byRow = $activities->groupBy('module_domain_id');
        $skillActivities = $items->pluck('activity_id')->filter()->flip();

        $ratings = DB::table('learner_developmental_checklists')
            ->whereIn('enrollment_id', $learners->pluck('enrollment_id'))
            ->where('quarter_id', $quarterId ?? 0)
            ->whereIn('checklist_item_id', $items->pluck('id'))
            ->get(['id', 'enrollment_id', 'checklist_item_id', 'rating', 'remarks', 'assessed_at'])
            ->keyBy(fn ($r) => $r->enrollment_id.'-'.$r->checklist_item_id);
        $results = self::latestResults($learners->pluck('enrollment_id'), $linked->pluck('activity_id')->merge($activities->pluck('id'))->unique()->values())
            ->keyBy(fn ($r) => $r->enrollment_id.'-'.$r->activity_id);

        // The checklist and result details come from the progress report's evidence(); its combined level is not used here.
        $entry = fn (string $kind, ?int $moduleDomainId, object $item, ?object $rating, ?object $result, ?int $activityQuarter) => Arr::only(
            ProgressReportDraft::evidence($item, $rating, $result),
            ['itemId', 'item', 'activityId', 'activityName', 'format', 'status', 'checklist', 'result'],
        ) + [
            'kind' => $kind,
            'moduleDomainId' => $moduleDomainId,
            // Only the teacher's checklist rating is a skill status; null means the teacher has not assessed it yet.
            'teacherStatus' => $rating?->rating,
            'linkedActivityQuarter' => $activityQuarter,
        ];

        // earned ÷ possible over assessed activities only; null, never 0%, when nothing is assessed.
        $module = function (object $row, int $enrollmentId) use ($byRow, $results) {
            $mine = $byRow->get($row->module_domain_id, collect());
            $counted = $mine->map(fn ($a) => ($r = $results->get($enrollmentId.'-'.$a->id)) ? self::points($r) : null)->filter();
            $earned = $counted->sum(0);
            $possible = $counted->sum(1);

            return [
                'moduleDomainId' => $row->module_domain_id,
                'total' => $mine->count(),
                'assessed' => $counted->count(),
                'earned' => $earned,
                'possible' => $possible,
                'percent' => $possible > 0 ? (int) round($earned * 100 / $possible) : null,
            ];
        };

        return response()->json([
            'quarter' => $quarter,
            'rows' => $rows->map(fn ($r) => ['moduleDomainId' => $r->module_domain_id, 'subject' => $r->subject, 'subheading' => $r->subheading])->values(),
            'learners' => $learners->map(fn ($l) => [
                'learnerId' => $l->id,
                'firstName' => $l->first_name,
                'middleName' => $l->middle_name,
                'lastName' => $l->last_name,
                'extensionName' => $l->extension_name,
                'modules' => $rows->map(fn ($r) => $module($r, $l->enrollment_id))->values(),
                'entries' => $items->map(fn ($i) => $entry(
                    'skill',
                    ProgressReportDraft::place($rows, $byDomain, $i)?->module_domain_id,
                    $i,
                    $ratings->get($l->enrollment_id.'-'.$i->id),
                    isset($linkedIds[$i->id]) ? $results->get($l->enrollment_id.'-'.$i->activity_id) : null,
                    $i->activity_quarter !== null ? (int) $i->activity_quarter : null,
                ))->concat($activities->reject(fn ($a) => isset($skillActivities[$a->id]))->map(fn ($a) => $entry(
                    'activity',
                    $a->module_domain_id,
                    (object) ['id' => null, 'item' => null, 'activity_id' => $a->id, 'activity_name' => $a->name, 'activity_format' => $a->activity_format],
                    null,
                    $results->get($l->enrollment_id.'-'.$a->id),
                    $quarter,
                )))->values(),
            ]),
        ]);
    }

    // Classroom Record points: a digital score as it is (a zero too), a rated practical on a 3-point scale, an unrated one not yet.
    public static function points(object $result): ?array
    {
        return $result->max_score !== null
            ? [(float) $result->score, (float) $result->max_score]
            : ($result->practical_rating ? [(float) self::PRACTICAL_POINTS[$result->practical_rating], 3.0] : null);
    }

    // Newest finished attempt first, so the first one kept per learner and activity is the latest result.
    public static function latestResults(Collection $enrollmentIds, Collection $activityIds): Collection
    {
        return DB::table('learner_activity_attempts')
            ->join('learner_activity_assignments', 'learner_activity_assignments.id', '=', 'learner_activity_attempts.assignment_id')
            ->whereIn('learner_activity_assignments.enrollment_id', $enrollmentIds)
            ->whereIn('learner_activity_assignments.activity_id', $activityIds)
            ->join('quarters', 'quarters.id', '=', 'learner_activity_assignments.quarter_id')
            ->whereIn('learner_activity_attempts.status', ['evaluated', 'submitted'])
            ->orderByRaw('coalesce(learner_activity_attempts.evaluated_at, learner_activity_attempts.submitted_at) desc')
            ->get([
                'learner_activity_assignments.enrollment_id', 'learner_activity_assignments.activity_id', 'learner_activity_attempts.score', 'learner_activity_attempts.max_score', 'learner_activity_attempts.practical_rating',
                // Extra detail the progress report keeps as evidence: which try, the teacher's note, and the quarter it was played in.
                'learner_activity_attempts.id as attempt_id', 'learner_activity_attempts.remarks', 'quarters.quarter_number as played_quarter',
                DB::raw('coalesce(learner_activity_attempts.evaluated_at, learner_activity_attempts.submitted_at) as finished_at'),
            ])
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
