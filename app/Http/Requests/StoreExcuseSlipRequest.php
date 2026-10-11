<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExcuseSlipRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Pick the day that was excused.',
            'date.date_format' => 'Pick the day that was excused.',
            'reason.required' => 'Write the reason on the excuse slip.',
            'reason.min' => 'The reason is too short.',
            'reason.max' => 'The reason must be 255 characters or fewer.',
        ];
    }
}
