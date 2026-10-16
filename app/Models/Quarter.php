<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 */
class Quarter extends Model
{
    protected $fillable = ['school_year_id', 'quarter_number', 'name', 'start_date', 'end_date', 'status'];

    // Same labels the curriculum and dashboard already show.
    public const NAMES = [1 => '1st Quarter', 2 => '2nd Quarter', 3 => '3rd Quarter', 4 => '4th Quarter'];

    // Key of the advisory lock behind lockState(); any constant works as long as nothing else uses it.
    public const STATE_LOCK = 410001;

    // One lock for "which quarter is active" and "which content has records". Quarter and school year
    // activation, curriculum writes, class start (the first classroom use of a lesson or activity) and
    // checklist ratings take it first inside their transaction, so neither can change between a check
    // and the write that relies on it. Released when the transaction ends.
    // An advisory lock rather than the year row: with no active year there is no row to lock.
    public static function lockState(): void
    {
        DB::statement('SELECT pg_advisory_xact_lock(?)', [self::STATE_LOCK]);
    }

    // Status (upcoming|active|completed) of each quarter the active school year has configured, keyed 1-4.
    // A quarter without a row there is left out, as is everything when no year is active.
    public static function statuses(): array
    {
        return self::whereIn('school_year_id', SchoolYear::where('status', 'active')->select('id'))
            ->pluck('status', 'quarter_number')->all();
    }

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function toApiArray(): array
    {
        return [
            'quarterId' => $this->id,
            'schoolYearId' => $this->school_year_id,
            'quarterNumber' => $this->quarter_number,
            'name' => $this->name,
            'startDate' => $this->start_date->toDateString(),
            'endDate' => $this->end_date->toDateString(),
            'status' => $this->status,
        ];
    }
}
