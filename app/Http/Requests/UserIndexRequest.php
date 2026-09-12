<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'status' => ['nullable', Rule::enum(StatusEnum::class)],
            'search' => ['nullable', 'string', 'max:150'],

        ];
    }
}
