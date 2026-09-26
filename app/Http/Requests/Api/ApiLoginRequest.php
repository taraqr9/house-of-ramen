<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ApiLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            // Identifies the token (e.g. "Kitchen Tablet 1"); logging in
            // again from the same device replaces its old token.
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
