<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Section extends Model
{
    public $timestamps = false;

    protected $fillable = ['school_year_id', 'teacher_id', 'name', 'grade_level', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    // Shape the frontend's Section type expects.
    public function toApiArray(): array
    {
        return [
            'sectionId' => $this->id,
            'gradeLevel' => $this->grade_level,
            'sectionName' => $this->name,
        ];
    }
}
