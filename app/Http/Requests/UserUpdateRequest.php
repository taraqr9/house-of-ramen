<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('is_active') == null) {
            $this->merge([
                'is_active' => 0,
            ]);
        }

        $this->merge([
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($userId),
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'role' => array_filter([
                'nullable',
                'exists:roles,name',
                auth()->user()->hasRole('Super Admin') ? null : Rule::notIn(['Super Admin']),
            ]),

            'password' => [
                'nullable',
                'string',
                'min:6',
                'confirmed',
            ],
            'remarks' => ['nullable', 'string'],
            'is_active' => ['nullable', 'bool'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
