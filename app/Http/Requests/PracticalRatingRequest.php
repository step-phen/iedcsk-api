<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PracticalRatingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'learnerId' => ['required', 'integer'],
            'activityId' => ['required', 'integer'],
            'result' => ['required', Rule::in(['needs_help', 'good', 'very_good'])],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['result.required' => 'Pick a rating.', 'result.in' => 'Pick a rating.'];
    }
}
