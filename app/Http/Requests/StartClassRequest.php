<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class StartClassRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'moduleId' => ['required', 'integer'],
            'lessonId' => ['required', 'integer'],
            'activityId' => ['required', 'integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // The activity must be active and sit under the picked lesson of the picked module.
            $matches = DB::table('activities')
                ->join('lessons', 'lessons.id', '=', 'activities.lesson_id')
                ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
                ->where('activities.id', $this->integer('activityId'))
                ->where('lessons.id', $this->integer('lessonId'))
                ->where('module_domains.module_id', $this->integer('moduleId'))
                ->where('activities.is_active', true)
                ->where('lessons.is_active', true)
                ->exists();
            if (! $matches) {
                $validator->errors()->add('activityId', 'Pick an activity from the selected lesson.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'moduleId.required' => 'Pick a module.',
            'lessonId.required' => 'Pick a lesson.',
            'activityId.required' => 'Pick an activity.',
        ];
    }
}
