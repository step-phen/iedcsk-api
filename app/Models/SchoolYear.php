<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
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

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function toApiArray(): array
    {
        return [
            'schoolYearId' => $this->id,
            'name' => $this->name,
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'status' => $this->status,
            // The fixed sections (Joy, Faith) already set up for this year; set once, at activation.
            'sections' => array_values(array_intersect(
                array_keys(Section::CHOICES),
                $this->sections->map(fn (Section $s) => Section::choice($s->name))->all(),
            )),
        ];
    }
}
