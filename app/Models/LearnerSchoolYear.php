<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerSchoolYear extends Model
{
    protected $fillable = ['school_year_id', 'section_id', 'enrolled_on', 'status'];

    protected function casts(): array
    {
        return ['enrolled_on' => 'date'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
