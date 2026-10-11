<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassroomRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'moduleId' => ['nullable', 'integer'],
            'domainId' => ['nullable', 'integer'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
        ];
    }
}
