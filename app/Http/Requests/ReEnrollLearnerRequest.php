<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

// Re-enrolling reuses the learner's profile, so only the enrollment fields of the Add Learner form are taken,
// with the same rules: one of this teacher's sections in the active school year.
class ReEnrollLearnerRequest extends StoreLearnerRequest
{
    private const FIELDS = ['gradeLevel', 'section', 'schoolYear'];

    public function rules(): array
    {
        return Arr::only(parent::rules(), self::FIELDS);
    }

    public function messages(): array
    {
        return Arr::where(parent::messages(), fn ($message, $key) => in_array(Str::before($key, '.'), self::FIELDS, true));
    }
}
