<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhoneIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('phone-view');
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:150'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'status' => ['nullable', 'string', 'in:upcoming,available,discontinued'],
            'category' => ['nullable', 'string', 'max:50'],
            'low_confidence' => ['nullable', 'boolean'],
            'no_image' => ['nullable', 'boolean'],
            'no_price' => ['nullable', 'boolean'],
        ];
    }
}
