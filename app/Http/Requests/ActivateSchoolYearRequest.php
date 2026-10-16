<?php

namespace App\Http\Requests;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ActivateSchoolYearRequest extends FormRequest
{
    // "joy" and "Joy" are the same pick, so `distinct` catches both; non-strings are left for the string rule.
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('sections'))) {
            $this->merge(['sections' => array_map(
                fn ($name) => is_string($name) ? (Section::choiceFromName($name) ?? $name) : $name,
                $this->input('sections'),
            )]);
        }
    }

    // A year's sections are picked once, when it is first set up; after that they can't change.
    public function rules(): array
    {
        $year = $this->route('schoolYear');
        // Only the missing dates are reported for an undated year (see after()).
        if (! $year->start_date || ! $year->end_date) {
            return [];
        }
        if ($year->sections()->exists()) {
            return ['sections' => ['prohibited']];
        }

        return [
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'string', 'distinct', 'in:'.implode(',', array_keys(Section::CHOICES))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $year = $this->route('schoolYear');
            if (! $year->start_date || ! $year->end_date) {
                $validator->errors()->add('schoolYear', 'Please set the school year start and end dates before activating it.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'sections.required' => 'Choose Joy, Faith, or both for this school year.',
            'sections.min' => 'Choose Joy, Faith, or both for this school year.',
            'sections.array' => 'Choose Joy, Faith, or both for this school year.',
            'sections.prohibited' => Section::SET_UP_MESSAGE,
            'sections.*.string' => 'Sections must be Joy or Faith.',
            'sections.*.required' => 'Sections must be Joy or Faith.',
            'sections.*.in' => 'Sections must be Joy or Faith.',
            'sections.*.distinct' => 'Each section can be picked once.',
        ];
    }
}
