<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosOrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('order-edit');
    }

    public function rules(): array
    {
        return [
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'general_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
