<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// The date is checked against the enrollment and its records in LearnerController::withdraw, under the enrollment lock.
class WithdrawLearnerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'withdrawnOn' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'withdrawnOn.required' => 'Pick the day the learner left.',
            'withdrawnOn.date_format' => 'Pick the day the learner left.',
            'reason.max' => 'The reason must be 255 characters or fewer.',
        ];
    }
}
