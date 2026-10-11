<?php

namespace App\Http\Controllers;

use App\Models\Quarter;
use App\Models\SchoolYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// School years are institution-wide; the teacher acts as the administrator.
class SchoolYearController extends Controller
{
    public function index(): JsonResponse
    {
        // Undated (future) years first, then newest first.
        return response()->json(SchoolYear::orderByRaw('start_date DESC NULLS FIRST')->orderByDesc('name')->get()
            ->map(fn (SchoolYear $y) => $y->toApiArray()));
    }

    public function active(): JsonResponse
    {
        // fromJsonString keeps "no active year" a literal null; response()->json(null) would send "{}".
        return ($year = SchoolYear::active())
            ? response()->json($year->toApiArray())
            : JsonResponse::fromJsonString('null');
    }

    // Dates are optional: a future year can be created before its official dates are known.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => $this->nameRules(),
            'startDate' => ['nullable', 'date_format:Y-m-d', 'required_with:endDate'],
            'endDate' => ['nullable', 'date_format:Y-m-d', 'required_with:startDate', 'after:startDate'],
        ], self::MESSAGES);

        // Status is left to the column default (upcoming); activating is a separate action.
        $year = SchoolYear::create([
            'name' => $data['name'],
            'start_date' => $data['startDate'] ?? null,
            'end_date' => $data['endDate'] ?? null,
        ]);

        return response()->json($year->refresh()->toApiArray(), 201);
    }

    // Everything references school years by id, so renaming is safe; status is never touched here.
    // An omitted field keeps its stored value; startDate/endDate sent as null clear the dates,
    // which only an upcoming year without quarters allows.
    public function update(Request $request, SchoolYear $schoolYear): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', ...$this->nameRules($schoolYear)],
            'startDate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'endDate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ], self::MESSAGES);

        $name = $data['name'] ?? $schoolYear->name;
        $start = array_key_exists('startDate', $data) ? $data['startDate'] : $schoolYear->start_date?->toDateString();
        $end = array_key_exists('endDate', $data) ? $data['endDate'] : $schoolYear->end_date?->toDateString();

        if ($start === null && $end !== null) {
            throw ValidationException::withMessages(['startDate' => 'Set both dates, or leave both empty.']);
        }
        if ($end === null && $start !== null) {
            throw ValidationException::withMessages(['endDate' => 'Set both dates, or leave both empty.']);
        }
        // Y-m-d strings compare as dates.
        if ($start !== null && $end <= $start) {
            throw ValidationException::withMessages(['endDate' => self::MESSAGES['endDate.after']]);
        }

        // Quarters must stay inside their school year, which the DB doesn't enforce across tables.
        $quarters = DB::table('quarters')->where('school_year_id', $schoolYear->id)
            ->selectRaw('count(*) as total, min(start_date) as first_start, max(end_date) as last_end')->first();
        if ($start === null) {
            if ($schoolYear->status !== 'upcoming') {
                throw ValidationException::withMessages(['startDate' => 'Active and completed school years need start and end dates.']);
            }
            if ($quarters->total > 0) {
                throw ValidationException::withMessages(['startDate' => "This school year has quarters, so its dates can't be removed."]);
            }
        } else {
            if ($quarters->first_start && $start > $quarters->first_start) {
                throw ValidationException::withMessages(['startDate' => "A quarter starts on {$quarters->first_start}, so the start date can't be later."]);
            }
            if ($quarters->last_end && $end < $quarters->last_end) {
                throw ValidationException::withMessages(['endDate' => "A quarter ends on {$quarters->last_end}, so the end date can't be earlier."]);
            }
        }

        $datesChanged = $schoolYear->start_date?->toDateString() !== $start
            || $schoolYear->end_date?->toDateString() !== $end;

        DB::transaction(function () use ($schoolYear, $name, $start, $end, $datesChanged) {
            $schoolYear->update([
                'name' => $name,
                'start_date' => $start,
                'end_date' => $end,
            ]);

            // Section timetables are created with the year's dates as their validity range
            // (SectionController::store), so they follow the year when its dates change.
            // Only the two dates move; times, days and is_active are left alone.
            // Undated years never have sections (only the active, dated year does).
            if ($datesChanged && $start !== null) {
                DB::table('class_schedules')
                    ->whereIn('section_module_id', DB::table('section_modules')
                        ->join('sections', 'sections.id', '=', 'section_modules.section_id')
                        ->where('sections.school_year_id', $schoolYear->id)
                        ->select('section_modules.id'))
                    ->update([
                        'effective_from' => $start,
                        'effective_until' => $end,
                        'updated_at' => now(),
                    ]);
            }
        });

        return response()->json($schoolYear->toApiArray());
    }

    // The only place status changes after creation (new years start as upcoming); nothing
    // recalculates it from dates later, so the stored status is what every screen shows.
    //   requested year -> active
    //   previous active year -> completed if its end_date is before today in Asia/Manila
    //                           (a year ending today is not over yet), otherwise upcoming
    // Only the active year may hold an active quarter, so the previous year's active quarter
    // is demoted by the same rule. The new year's quarters are left as stored: none is
    // activated for it, and one already stored as active stays active.
    // school_years_one_active rejects two active rows, so the previous year is demoted
    // before the new one is promoted, all in one transaction.
    public function activate(SchoolYear $schoolYear): JsonResponse
    {
        if (! $schoolYear->start_date || ! $schoolYear->end_date) {
            throw ValidationException::withMessages(['schoolYear' => 'Please set the school year start and end dates before activating it.']);
        }

        DB::transaction(function () use ($schoolYear) {
            $previous = SchoolYear::where('status', 'active')->lockForUpdate()->first();
            if ($previous?->is($schoolYear)) {
                return;
            }

            if ($previous) {
                // Compare calendar dates as Y-m-d strings so the app timezone can't shift the result.
                $today = Carbon::now('Asia/Manila')->toDateString();
                $demote = fn (SchoolYear|Quarter $row) => $row->update(['status' => $row->end_date->toDateString() < $today ? 'completed' : 'upcoming']);

                $demote($previous);
                $quarter = Quarter::where('school_year_id', $previous->id)->where('status', 'active')->lockForUpdate()->first();
                if ($quarter) {
                    $demote($quarter);
                }
            }
            $schoolYear->update(['status' => 'active']);
        });

        return response()->json($schoolYear->refresh()->toApiArray());
    }

    private const MESSAGES = [
        'name.regex' => 'School year must be like 2026-2027.',
        'name.unique' => 'This school year already exists.',
        'startDate.required_with' => 'Set both dates, or leave both empty.',
        'endDate.required_with' => 'Set both dates, or leave both empty.',
        'endDate.after' => 'End date must be after the start date.',
    ];

    private function nameRules(?SchoolYear $ignore = null): array
    {
        // Stored as 2026-2027; StoreLearnerRequest matches the active year by this exact format.
        return ['required', 'string', 'regex:/^\d{4}-\d{4}$/', Rule::unique('school_years', 'name')->ignore($ignore)];
    }
}
