<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 */
class SchoolYear extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'status'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public static function active(): ?self
    {
        return static::where('status', 'active')->first();
    }

    public function toApiArray(): array
    {
        return [
            'schoolYearId' => $this->id,
            'name' => $this->name,
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'status' => $this->status,
        ];
    }
}
