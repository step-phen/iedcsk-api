<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerAddress extends Model
{
    protected $fillable = ['house_street', 'barangay', 'city_municipality', 'province_id', 'zip_code'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
