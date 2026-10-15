<?php

namespace App\Http\Controllers;

use App\Models\Quarter;
use App\Models\SchoolYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Quarters always belong to one school year, addressed by their number (1-4) within it.
class QuarterController extends Controller
{
    public function index(SchoolYear $schoolYear): JsonResponse
    {
        return response()->json(
            Quarter::where('school_year_id', $schoolYear->id)->orderBy('quarter_number')->get()
                ->map(fn (Quarter $q) => $q->toApiArray())
        );
    }

    // Creates the quarter or changes its dates. The name comes from the number, and status is
    // never touched here: new quarters start as upcoming, activating is a separate action.
    public function save(Request $request, SchoolYear $schoolYear, int $number): JsonResponse
    {
        $data = $request->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after:startDate'],
        ], [
            'endDate.after' => 'End date must be after the start date.',
        ]);

        $this->requireYearDates($schoolYear, 'startDate');
        $this->checkDates($schoolYear, [$number => $data], fn (int $n, string $field) => $field);

        $quarter = $this->store($schoolYear, $number, $data);

        return response()->json($quarter->toApiArray(), $quarter->wasRecentlyCreated ? 201 : 200);
    }

    // Saves Quarter 1-4 from one form, sent as a list of four {startDate, endDate} in order.
    // A blank quarter stays unconfigured, but a configured one can't be blanked. Same rules
    // as save(): names come from the number, status is never touched, nothing is activated.
    public function saveAll(Request $request, SchoolYear $schoolYear): JsonResponse
    {
        $data = $request->validate([
            'quarters' => ['required', 'list', 'size:4'],
            'quarters.*.startDate' => ['nullable', 'date_format:Y-m-d', 'required_with:quarters.*.endDate'],
            'quarters.*.endDate' => ['nullable', 'date_format:Y-m-d', 'required_with:quarters.*.startDate'],
        ], [
            'quarters.*.startDate.required_with' => 'Set both dates, or leave both empty.',
            'quarters.*.endDate.required_with' => 'Set both dates, or leave both empty.',
        ]);

        $this->requireYearDates($schoolYear, 'quarters');

        $stored = Quarter::where('school_year_id', $schoolYear->id)->get()->keyBy('quarter_number');
        $dates = [];
        $errors = [];
        foreach ($data['quarters'] as $i => $q) {
            $number = $i + 1;
            if (($q['startDate'] ?? null) === null) {
                if ($stored->has($number)) {
                    $errors["quarters.$i.startDate"] = "{$stored[$number]->name} is already configured, so its dates can't be removed.";
                }

                continue;
            }
            $dates[$number] = ['startDate' => $q['startDate'], 'endDate' => $q['endDate']];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $this->checkDates($schoolYear, $dates, fn (int $n, string $field) => 'quarters.'.($n - 1).".$field");

        DB::transaction(function () use ($schoolYear, $dates) {
            foreach ($dates as $number => $d) {
                $this->store($schoolYear, $number, $d);
            }
        });

        return $this->index($schoolYear);
    }

    // An upcoming year may not have its official dates yet; quarters need them as bounds.
    private function requireYearDates(SchoolYear $schoolYear, string $key): void
    {
        if (! $schoolYear->start_date || ! $schoolYear->end_date) {
            throw ValidationException::withMessages([$key => "Set the school year's start and end dates first."]);
        }
    }

    // Checks the quarters being saved ($dates: number => startDate/endDate) against the school
    // year and every other configured quarter, saved now or stored, so Q1 < Q2 < Q3 < Q4 holds
    // with no overlap. $key names the error field for a quarter number and field.
    private function checkDates(SchoolYear $schoolYear, array $dates, callable $key): void
    {
        // Y-m-d strings compare as dates.
        $yearStart = $schoolYear->start_date->toDateString();
        $yearEnd = $schoolYear->end_date->toDateString();

        $all = Quarter::where('school_year_id', $schoolYear->id)->get()->mapWithKeys(fn (Quarter $q) => [$q->quarter_number => [
            'startDate' => $q->start_date->toDateString(), 'endDate' => $q->end_date->toDateString(),
        ]])->all();
        $all = array_replace($all, $dates);

        $errors = [];
        foreach ($dates as $number => $d) {
            $start = $key($number, 'startDate');
            $end = $key($number, 'endDate');
            if ($d['startDate'] < $yearStart) {
                $errors[$start] ??= "The school year starts on {$yearStart}.";
            }
            if ($d['endDate'] > $yearEnd) {
                $errors[$end] ??= "The school year ends on {$yearEnd}.";
            }
            if ($d['endDate'] <= $d['startDate']) {
                $errors[$end] ??= 'End date must be after the start date.';
            }
            // Earlier quarters must end before this one starts and later ones start after it ends,
            // which also rules out any overlap.
            foreach ($all as $other => $o) {
                $name = Quarter::NAMES[$other];
                if ($other < $number && $o['endDate'] >= $d['startDate']) {
                    $errors[$start] ??= "{$name} ends on {$o['endDate']}, so this quarter must start after it.";
                }
                if ($other > $number && $o['startDate'] <= $d['endDate']) {
                    $errors[$end] ??= "{$name} starts on {$o['startDate']}, so this quarter must end before it.";
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function store(SchoolYear $schoolYear, int $number, array $d): Quarter
    {
        $quarter = Quarter::firstOrNew(['school_year_id' => $schoolYear->id, 'quarter_number' => $number]);
        $quarter->fill(['name' => Quarter::NAMES[$number], 'start_date' => $d['startDate'], 'end_date' => $d['endDate']]);
        $quarter->status ??= 'upcoming';
        $quarter->save();

        return $quarter;
    }

    // The only place a quarter's status changes after creation, mirroring school years:
    //   requested quarter -> active
    //   previous active quarter of the same year -> completed if its end_date is before today
    //                                               in Asia/Manila, otherwise upcoming
    // Only the active school year's quarters can be activated, since that year is the one
    // the dashboard reads. quarters_one_active_per_school_year backs this up in the DB.
    public function activate(SchoolYear $schoolYear, int $number): JsonResponse
    {
        $quarter = Quarter::where('school_year_id', $schoolYear->id)->where('quarter_number', $number)->firstOrFail();

        DB::transaction(function () use ($schoolYear, $quarter) {
            // Taken first, as every write that depends on the active quarter does (Quarter::lockState).
            Quarter::lockState();
            // Checked under the year's row lock, which school year activation also takes, so a
            // year switch can't slip in between the check and the update.
            if (SchoolYear::whereKey($schoolYear->id)->lockForUpdate()->value('status') !== 'active') {
                throw ValidationException::withMessages(['quarter' => 'Only quarters of the active school year can be set as active.']);
            }

            $previous = Quarter::where('school_year_id', $schoolYear->id)->where('status', 'active')->lockForUpdate()->first();
            if ($previous?->is($quarter)) {
                return;
            }

            if ($previous) {
                // Compare calendar dates as Y-m-d strings so the app timezone can't shift the result.
                $ended = $previous->end_date->toDateString() < Carbon::now('Asia/Manila')->toDateString();
                $previous->update(['status' => $ended ? 'completed' : 'upcoming']);
            }
            $quarter->update(['status' => 'active']);
        });

        return response()->json($quarter->refresh()->toApiArray());
    }
}
