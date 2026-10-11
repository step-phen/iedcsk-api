<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    // Usernames are stored lowercase (a DB check enforces it), and login lowercases what is typed.
    protected function prepareForValidation(): void
    {
        $this->merge(['username' => strtolower((string) $this->input('username'))]);
    }

    public function rules(): array
    {
        return [
            'teacherName' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash:ascii', Rule::unique('teachers', 'username')->ignore($this->user()->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'teacherName.required' => 'Teacher name is required.',
            'username.required' => 'Username is required.',
            'username.alpha_dash' => 'Use only letters, numbers, dashes and underscores.',
            'username.unique' => 'That username is already taken.',
        ];
    }
}
