<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $started_at
 */
class SchoolYear extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'status'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'started_at' => 'datetime'];
    }

    // Records that teaching began in this year: its first quarter activation, attendance mark, checklist rating
    // or progress report. Call inside that write's transaction, so it only sticks if the write does. Set once,
    // never cleared. The row lock it takes (first time only) is what storeModule waits on before it checks.
    // Through DB::table so the year's updated_at stays as it was.
    public static function markStarted(int $id): void
    {
        DB::table('school_years')->where('id', $id)->whereNull('started_at')->update(['started_at' => now()]);
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
            // The fixed sections (Joy, Faith) already set up for this year, by stored identity so a renamed one still counts.
            'sections' => array_values(array_intersect(array_keys(Section::CHOICES), $this->sections->pluck('choice')->filter()->all())),
        ];
    }
}
