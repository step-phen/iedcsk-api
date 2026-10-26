<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PracticalRatingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'learnerId' => ['required', 'integer'],
            'activityId' => ['required', 'integer'],
            'result' => ['required', 'integer', 'between:1,5'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['result.required' => 'Pick a rating.', 'result.integer' => 'Pick a rating.', 'result.between' => 'Pick a rating from 1 to 5.'];
    }
}
