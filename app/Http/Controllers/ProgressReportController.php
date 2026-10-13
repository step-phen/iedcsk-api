<?php

namespace App\Http\Controllers;

use App\Models\Quarter;
use App\Models\Teacher;
use App\Support\ProgressReportDraft;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Individual learner progress report per quarter: a rule-based draft the teacher edits, then finalizes into a
// numbered snapshot. Reports belong to whoever owns the learner's section now, so a handover carries them along.
class ProgressReportController extends Controller
{
    // The section's learners with their report status for one quarter.
    public function index(Request $request, #[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);
        $request->validate(['quarter' => ['sometimes', 'integer', 'between:1,4']]);
        $quarters = DB::table('quarters')->where('school_year_id', $section->school_year_id)->get(['id', 'quarter_number', 'status'])->keyBy('quarter_number');
        $quarter = $request->integer('quarter') ?: ($quarters->firstWhere('status', 'active')?->quarter_number ?? 1);
        $quarterId = $quarters->get($quarter)?->id;

        $learners = DB::table('learner_school_years')
            ->join('learners', 'learners.id', '=', 'learner_school_years.learner_id')
            ->leftJoin('quarterly_progress_reports', fn ($j) => $j->on('quarterly_progress_reports.enrollment_id', '=', 'learner_school_years.id')
                ->where('quarterly_progress_reports.quarter_id', $quarterId ?? 0))
            ->where('learner_school_years.section_id', $section->id)
            // Learners who left are listed only when they already have a report to look back on.
            ->where(fn ($q) => $q->whereIn('learner_school_years.status', ['active', 'completed'])->orWhereNotNull('quarterly_progress_reports.id'))
            ->orderBy('learners.last_name')->orderBy('learners.first_name')
            ->get(['learner_school_years.id as enrollment_id', 'learner_school_years.status', 'learners.id', 'learners.first_name', 'learners.middle_name', 'learners.last_name', 'learners.extension_name', 'quarterly_progress_reports.id as report_id', 'quarterly_progress_reports.status as report_status']);

        return response()->json([
            'quarter' => $quarter,
            'quarters' => array_map(fn (int $n) => ['quarter' => $n, 'quarterName' => Quarter::NAMES[$n], 'available' => $quarters->has($n)], [1, 2, 3, 4]),
            'learners' => $learners->map(fn ($l) => [
                'enrollmentId' => $l->enrollment_id,
                'learnerId' => $l->id,
                'firstName' => $l->first_name,
                'middleName' => $l->middle_name,
                'lastName' => $l->last_name,
                'extensionName' => $l->extension_name,
                'enrollmentStatus' => $l->status,
                'reportId' => $l->report_id,
                'reportStatus' => $l->report_status,
            ]),
        ]);
    }

    // Generates the draft, or returns the one already there so a double click never makes two.
    public function store(Request $request, #[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);
        $data = $request->validate([
            'enrollmentId' => ['required', 'integer', Rule::exists('learner_school_years', 'id')->where('section_id', $section->id)->whereIn('status', ['active', 'completed'])],
            'quarter' => ['required', 'integer', 'between:1,4', Rule::exists('quarters', 'quarter_number')->where('school_year_id', $section->school_year_id)],
        ], ['quarter.exists' => 'This quarter has not been set up for the school year yet.']);
        $quarterId = DB::table('quarters')->where(['school_year_id' => $section->school_year_id, 'quarter_number' => $data['quarter']])->value('id');

        [$id, $created] = DB::transaction(function () use ($data, $quarterId, $teacher) {
            $key = ['enrollment_id' => $data['enrollmentId'], 'quarter_id' => $quarterId];
            // A concurrent insert of the same key waits for this one and is then ignored.
            $created = DB::table('quarterly_progress_reports')->insertOrIgnore($key + ['teacher_id' => $teacher->id, 'status' => 'draft', 'generated_at' => now()]) > 0;
            $id = DB::table('quarterly_progress_reports')->where($key)->value('id');
            if ($created) {
                $draft = ProgressReportDraft::build($this->report($teacher, $id));
                DB::table('quarterly_progress_report_details')->insert(array_map(fn ($row, $moduleDomainId) => [
                    'report_id' => $id,
                    'module_domain_id' => $moduleDomainId,
                    'development_observed' => $row['observed'],
                    'generated_observed' => $row['observed'],
                    'evidence' => json_encode($row['evidence']),
                ], $draft['rows'], array_keys($draft['rows'])));
            }

            return [$id, $created];
        });

        return response()->json($this->payload($this->report($teacher, $id)), $created ? 201 : 200);
    }

    public function show(#[CurrentUser] Teacher $teacher, int $report): JsonResponse
    {
        return response()->json($this->payload($this->report($teacher, $report)));
    }

    // Saves the teacher's text for every row of a draft.
    public function update(Request $request, #[CurrentUser] Teacher $teacher, int $report): JsonResponse
    {
        $report = $this->report($teacher, $report);
        $rows = ProgressReportDraft::rowsOf($report->id)->keyBy('module_domain_id');
        $data = $request->validate([
            'updatedAt' => ['required', 'string'],
            'rows' => ['required', 'array', 'size:'.$rows->count()],
            'rows.*.moduleDomainId' => ['required', 'integer', 'distinct', Rule::in($rows->keys()->all())],
            'rows.*.developmentObserved' => ['present', 'nullable', 'string', 'max:2000'],
            'rows.*.commentsRemarks' => ['nullable', 'string', 'max:1500'],
        ], ['rows.size' => 'Send every row of the report.']);

        $errors = [];
        foreach ($data['rows'] as $i => $row) {
            if (filled($row['commentsRemarks'] ?? null) && ! $rows[$row['moduleDomainId']]->remarks_row) {
                $errors["rows.{$i}.commentsRemarks"] = 'This subject shares one remarks box; write it on its first row.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($report, $data) {
            $this->lockEditable($report->id, $data['updatedAt']);
            foreach ($data['rows'] as $row) {
                DB::table('quarterly_progress_report_details')
                    ->where(['report_id' => $report->id, 'module_domain_id' => $row['moduleDomainId']])
                    ->update(['development_observed' => $row['developmentObserved'] ?? '', 'comments_remarks' => $row['commentsRemarks'] ?? null, 'updated_at' => now()]);
            }
            DB::table('quarterly_progress_reports')->where('id', $report->id)->update(['updated_at' => now()]);
        });

        return response()->json($this->payload($this->report($teacher, $report->id)));
    }

    // Rebuilds the draft from current evidence. Rows the teacher edited keep their text; only the stored draft beside them changes.
    // Comments/Remarks are the teacher's alone and are never touched here.
    public function regenerate(#[CurrentUser] Teacher $teacher, int $report): JsonResponse
    {
        $report = $this->report($teacher, $report);

        DB::transaction(function () use ($report) {
            $this->lockEditable($report->id);
            $draft = ProgressReportDraft::build($report);
            $details = DB::table('quarterly_progress_report_details')->where('report_id', $report->id)->get()->keyBy('module_domain_id');
            foreach ($draft['rows'] as $moduleDomainId => $row) {
                $old = $details->get($moduleDomainId);
                $values = ['generated_observed' => $row['observed'], 'evidence' => json_encode($row['evidence'])];
                if (! $old) {
                    DB::table('quarterly_progress_report_details')->insert($values + ['report_id' => $report->id, 'module_domain_id' => $moduleDomainId, 'development_observed' => $row['observed']]);

                    continue;
                }
                DB::table('quarterly_progress_report_details')->where('id', $old->id)->update($values + [
                    'development_observed' => $old->development_observed === $old->generated_observed ? $row['observed'] : $old->development_observed,
                    'updated_at' => now(),
                ]);
            }
            DB::table('quarterly_progress_reports')->where('id', $report->id)->update(['generated_at' => now(), 'updated_at' => now()]);
        });

        return response()->json($this->payload($this->report($teacher, $report->id)));
    }

    // Freezes the report as it reads now into the next version.
    public function finalize(Request $request, #[CurrentUser] Teacher $teacher, int $report): JsonResponse
    {
        $report = $this->report($teacher, $report);
        $data = $request->validate(['updatedAt' => ['required', 'string']]);

        DB::transaction(function () use ($report, $data, $teacher) {
            $current = $this->lockEditable($report->id, $data['updatedAt']);
            $rows = $this->rows($report->id);
            $blank = $rows->filter(fn ($r) => trim($r['developmentObserved']) === '');
            if ($blank->isNotEmpty()) {
                throw ValidationException::withMessages(['rows' => 'Fill in Development Observed for '.$blank->map(fn ($r) => $r['subheading'] ?? $r['subject'])->join(', ', ' and ').' before finalizing.']);
            }

            $now = now();
            $version = (DB::table('quarterly_progress_report_versions')->where('report_id', $report->id)->max('version_number') ?? 0) + 1;
            DB::table('quarterly_progress_report_versions')->insert([
                'report_id' => $report->id,
                'version_number' => $version,
                'snapshot' => json_encode([
                    'version' => $version,
                    'header' => $this->header($report),
                    'rows' => $rows->values(),
                    'generatedAt' => $current->generated_at,
                    'finalizedAt' => $now->toIso8601String(),
                    'finalizedBy' => $teacher->name,
                    'correctionReason' => $current->correction_reason,
                ]),
                'finalized_by' => $teacher->id,
                'finalized_at' => $now,
                'correction_reason' => $current->correction_reason,
            ]);
            DB::table('quarterly_progress_reports')->where('id', $report->id)
                ->update(['status' => 'finalized', 'finalized_at' => $now, 'correction_reason' => null, 'updated_at' => $now]);
        });

        return response()->json($this->payload($this->report($teacher, $report->id)));
    }

    // Opens a finalized report for a correction; earlier versions stay as they were.
    public function reopen(Request $request, #[CurrentUser] Teacher $teacher, int $report): JsonResponse
    {
        $report = $this->report($teacher, $report);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($report, $data) {
            $current = DB::table('quarterly_progress_reports')->where('id', $report->id)->lockForUpdate()->first();
            abort_if($current->status !== 'finalized', 409, 'Only a finalized report can be reopened.');
            DB::table('quarterly_progress_reports')->where('id', $report->id)
                ->update(['status' => 'draft', 'finalized_at' => null, 'correction_reason' => $data['reason'], 'updated_at' => now()]);
        });

        return response()->json($this->payload($this->report($teacher, $report->id)));
    }

    // One finalized version exactly as it was stored.
    public function version(#[CurrentUser] Teacher $teacher, int $report, int $version): Response
    {
        $report = $this->report($teacher, $report);
        $snapshot = DB::table('quarterly_progress_report_versions')->where(['report_id' => $report->id, 'version_number' => $version])->value('snapshot');
        abort_if($snapshot === null, 404);

        return response($snapshot, 200, ['Content-Type' => 'application/json']);
    }

    // The report with everything around it, 404 unless the learner's section is this teacher's now.
    private function report(Teacher $teacher, int $id): object
    {
        $report = DB::table('quarterly_progress_reports')
            ->join('learner_school_years', 'learner_school_years.id', '=', 'quarterly_progress_reports.enrollment_id')
            ->join('learners', 'learners.id', '=', 'learner_school_years.learner_id')
            ->join('sections', 'sections.id', '=', 'learner_school_years.section_id')
            ->join('school_years', 'school_years.id', '=', 'sections.school_year_id')
            ->join('teachers', 'teachers.id', '=', 'sections.teacher_id')
            ->join('quarters', 'quarters.id', '=', 'quarterly_progress_reports.quarter_id')
            ->where(['quarterly_progress_reports.id' => $id, 'sections.teacher_id' => $teacher->id])
            ->first([
                'quarterly_progress_reports.id', 'quarterly_progress_reports.enrollment_id', 'quarterly_progress_reports.quarter_id', 'quarterly_progress_reports.status',
                'quarterly_progress_reports.generated_at', 'quarterly_progress_reports.finalized_at', 'quarterly_progress_reports.correction_reason', 'quarterly_progress_reports.updated_at',
                'learners.first_name', 'learners.middle_name', 'learners.last_name', 'learners.extension_name', 'learners.gender',
                'sections.id as section_id', 'sections.name as section_name', 'sections.grade_level', 'school_years.name as school_year', 'teachers.name as teacher_name', 'quarters.quarter_number',
            ]);
        abort_if($report === null, 404);

        return $report;
    }

    // Locks the report row for a write, refusing finalized reports and saves based on an older copy.
    // ponytail: updated_at is stored to the second, so two tabs saving within the same second are not told apart.
    private function lockEditable(int $id, ?string $updatedAt = null): object
    {
        $current = DB::table('quarterly_progress_reports')->where('id', $id)->lockForUpdate()->first();
        abort_if($current->status === 'finalized', 409, 'This report is finalized. Reopen it to make a correction.');
        abort_if($updatedAt !== null && $updatedAt !== $current->updated_at, 409, 'This report was changed somewhere else. Reload it to see the latest text.');

        return $current;
    }

    private function header(object $report): array
    {
        return [
            'firstName' => $report->first_name,
            'middleName' => $report->middle_name,
            'lastName' => $report->last_name,
            'extensionName' => $report->extension_name,
            'gender' => $report->gender,
            'gradeLevel' => $report->grade_level,
            'sectionName' => $report->section_name,
            'schoolYear' => $report->school_year,
            'quarter' => (int) $report->quarter_number,
            'quarterName' => Quarter::NAMES[$report->quarter_number],
            'teacherName' => $report->teacher_name,
        ];
    }

    // Saved rows in print order, in the shape both the editor and the snapshot use.
    private function rows(int $reportId)
    {
        $details = DB::table('quarterly_progress_report_details')->where('report_id', $reportId)->get()->keyBy('module_domain_id');

        return ProgressReportDraft::rowsOf($reportId)->map(function ($row) use ($details) {
            $detail = $details[$row->module_domain_id];

            return [
                'moduleDomainId' => $row->module_domain_id,
                'subject' => $row->subject,
                'subheading' => $row->subheading,
                'remarksRow' => $row->remarks_row,
                'developmentObserved' => $detail->development_observed,
                'commentsRemarks' => $detail->comments_remarks,
                'generatedObserved' => $detail->generated_observed,
                'evidence' => json_decode($detail->evidence, true),
            ];
        });
    }

    private function payload(object $report): array
    {
        return [
            'reportId' => $report->id,
            'enrollmentId' => $report->enrollment_id,
            'status' => $report->status,
            'updatedAt' => $report->updated_at,
            'generatedAt' => $report->generated_at,
            'finalizedAt' => $report->finalized_at,
            'correctionReason' => $report->correction_reason,
            'header' => $this->header($report),
            'rows' => $this->rows($report->id)->values(),
            // Skills of this quarter no row could claim, so the teacher still sees them.
            'unplaced' => $report->status === 'draft' ? ProgressReportDraft::build($report)['unplaced'] : [],
            'versions' => DB::table('quarterly_progress_report_versions')
                ->join('teachers', 'teachers.id', '=', 'quarterly_progress_report_versions.finalized_by')
                ->where('report_id', $report->id)->orderBy('version_number')
                ->get(['version_number', 'finalized_at', 'teachers.name', 'correction_reason'])
                ->map(fn ($v) => ['version' => $v->version_number, 'finalizedAt' => $v->finalized_at, 'finalizedBy' => $v->name, 'correctionReason' => $v->correction_reason]),
        ];
    }
}
