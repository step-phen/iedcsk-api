<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class AcceptHandoverRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'newPassword' => ['required', 'string', 'min:8', 'max:255'],
            'confirmPassword' => ['required', 'same:newPassword'],
        ];
    }

    // The temporary password was known to the outgoing teacher, so it can't be kept.
    public function after(): array
    {
        return [function (Validator $validator) {
            if (Hash::check((string) $this->input('newPassword'), $this->user()->password_hash)) {
                $validator->errors()->add('newPassword', 'Choose a password different from the temporary one.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'newPassword.required' => 'Enter a new password.',
            'newPassword.min' => 'New password must be at least 8 characters.',
            'confirmPassword.required' => 'Confirm your new password.',
            'confirmPassword.same' => 'Passwords do not match.',
        ];
    }
}
