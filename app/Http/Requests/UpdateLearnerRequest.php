<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

// The Edit Profile form: same rules as adding a learner, minus the fields it cannot change; guardians arrive as in the Add form.
class UpdateLearnerRequest extends StoreLearnerRequest
{
    private const EDITABLE = [
        'houseStreet', 'barangay', 'cityMunicipality', 'provinceId', 'zipCode', 'photo',
        'guardianCount',
    ];

    public function rules(): array
    {
        $rules = Arr::only(parent::rules(), self::EDITABLE)
            + ['lrn' => ['nullable', 'digits:12', Rule::unique('learners', 'lrn')->ignore($this->route('learner'))]];

        foreach ($this->guardianIndexes() as $i) {
            $rules += $this->guardianRules("_$i");
        }

        return $rules;
    }
}
