<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'moduleDomainId' => ['required', 'integer', Rule::exists('module_domains', 'id')->where('module_id', $this->route('module'))],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'quarter' => ['required', 'integer', 'between:1,4'],
            'week' => ['required', 'integer', 'between:1,20'],
            'day' => ['required', 'integer', 'between:1,7'],
            'video' => ['nullable', ...StoreLessonVideoRequest::VIDEO_RULES],
        ];
    }

    public function messages(): array
    {
        return [
            'moduleDomainId.required' => 'Pick a domain.',
            'moduleDomainId.exists' => 'Pick a domain of this module.',
            'title.required' => 'Lesson title is required.',
            'week.between' => 'Week must be between 1 and 20.',
            'day.required' => 'Pick a day.',
        ] + StoreLessonVideoRequest::VIDEO_MESSAGES;
    }
}
