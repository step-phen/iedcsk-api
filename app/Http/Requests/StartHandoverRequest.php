<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class StartHandoverRequest extends FormRequest
{
    // Usernames are stored lowercase (a DB check enforces it), and login lowercases what is typed.
    protected function prepareForValidation(): void
    {
        $this->merge(['username' => strtolower((string) $this->input('username'))]);
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'current_password:web'],
            'teacherName' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash:ascii', 'unique:teachers,username'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'confirmPassword' => ['required', 'same:password'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $teacherId = $this->user()->id;
            $open = DB::table('teacher_handovers')->where('status', 'pending')
                ->where(fn ($q) => $q->where('from_teacher_id', $teacherId)->orWhere('to_teacher_id', $teacherId))
                ->exists();
            if ($open) {
                $validator->errors()->add('username', 'A handover is already waiting. Cancel it first.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'currentPassword.required' => 'Enter your current password.',
            'currentPassword.current_password' => 'Current password is incorrect.',
            'teacherName.required' => "Enter the new teacher's name.",
            'username.required' => 'Username is required.',
            'username.alpha_dash' => 'Use only letters, numbers, dashes and underscores.',
            'username.unique' => 'That username is already taken.',
            'password.required' => 'Enter a temporary password.',
            'password.min' => 'Temporary password must be at least 8 characters.',
            'confirmPassword.required' => 'Confirm the temporary password.',
            'confirmPassword.same' => 'Passwords do not match.',
        ];
    }
}
