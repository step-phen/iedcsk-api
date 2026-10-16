<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSectionRequest extends FormRequest
{
    // "  Sampaguita   Class " is saved as "Sampaguita Class".
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sectionName'))) {
            $this->merge(['sectionName' => preg_replace('/\s+/u', ' ', trim($this->input('sectionName')))]);
        }
    }

    public function rules(): array
    {
        return [
            'sectionName' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'sectionName.required' => 'Enter a section name.',
            'sectionName.max' => 'Keep the section name to 50 characters or fewer.',
        ];
    }
}
