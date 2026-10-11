<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'current_password:web'],
            'newPassword' => ['required', 'string', 'min:8', 'max:255', 'different:currentPassword'],
            'confirmPassword' => ['required', 'same:newPassword'],
        ];
    }

    public function messages(): array
    {
        return [
            'currentPassword.required' => 'Enter your current password.',
            'currentPassword.current_password' => 'Current password is incorrect.',
            'newPassword.required' => 'Enter a new password.',
            'newPassword.min' => 'New password must be at least 8 characters.',
            'newPassword.different' => 'New password must differ from the current one.',
            'confirmPassword.required' => 'Confirm your new password.',
            'confirmPassword.same' => 'Passwords do not match.',
        ];
    }
}
