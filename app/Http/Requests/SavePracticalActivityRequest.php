<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePracticalActivityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            // The short line on the activity card, so teachers know what it is at a glance.
            'description' => ['required', 'string', 'max:500'],
            'instructions' => ['required', 'string', 'max:1000'],
            'teacherGuidelines' => ['required', 'string', 'max:1000'],
            // Optional: a teacher can add the activity first and attach the demonstration video later.
            // Editing keeps the current video unless a new one is sent.
            'video' => ['nullable', ...StoreLessonVideoRequest::VIDEO_RULES],
        ];
    }

    public function messages(): array
    {
        return StoreLessonVideoRequest::VIDEO_MESSAGES + [
            'name.required' => 'Give the activity a name.',
            'name.max' => 'Keep the name to 150 characters or fewer.',
            'description.required' => 'Describe the activity in a sentence.',
            'description.max' => 'Keep the description to 500 characters or fewer.',
            'instructions.required' => 'Tell the learners what to do.',
            'instructions.max' => 'Keep the instructions to 1000 characters or fewer.',
            'teacherGuidelines.required' => 'Write what you will look for when rating.',
            'teacherGuidelines.max' => 'Keep the rating guide to 1000 characters or fewer.',
        ];
    }
}
