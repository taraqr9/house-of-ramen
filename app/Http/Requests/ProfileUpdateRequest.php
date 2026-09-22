<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'avatar' => ['nullable', 'image', 'max:2048'],

            'password' => [
                'nullable',
                'string',
                'min:8',
                'max:15',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'Password must be at least 8 characters.',
            'password.max' => 'Password must not be more than 15 characters.',
            'password.confirmed' => 'New password and confirm password do not match.',
            'password.regex' => 'Password must include uppercase, lowercase, number, and special character.',
        ];
    }
}
