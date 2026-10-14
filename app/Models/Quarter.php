<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 */
class Quarter extends Model
{
    protected $fillable = ['school_year_id', 'quarter_number', 'name', 'start_date', 'end_date', 'status'];

    // Same labels the curriculum and dashboard already show.
    public const NAMES = [1 => '1st Quarter', 2 => '2nd Quarter', 3 => '3rd Quarter', 4 => '4th Quarter'];

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
