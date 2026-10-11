<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAttendanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.learnerId' => ['required', 'integer', 'distinct'],
            'entries.*.status' => ['required', Rule::in(['PRESENT', 'LATE', 'ABSENT'])],
        ];
    }

    public function messages(): array
    {
        return [
            'entries.required' => 'Mark at least one learner first.',
            'entries.min' => 'Mark at least one learner first.',
            'entries.*.status.in' => 'Mark each learner Present, Late or Absent.',
        ];
    }
}
