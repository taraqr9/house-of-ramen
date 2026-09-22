<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
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
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:255',
                'unique:users,username',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'role' => array_filter([
                'nullable',
                'exists:roles,name',
                auth()->user()->hasRole('Super Admin') ? null : Rule::notIn(['Super Admin']),
            ]),

            'password' => [
                'required',
                'string',
                'min:6',
                'confirmed',
            ],
            'remarks' => ['nullable', 'string'],
            'is_active' => ['nullable', Rule::enum(StatusEnum::class)],
            'created_by' => ['nullable', 'exists:users,id'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
