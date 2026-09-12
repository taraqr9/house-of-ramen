<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class BrandStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('brand-create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('name', '')),
            'is_active' => (int) $this->input('is_active', 0),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', 'unique:brands,name'],
            'slug' => ['required', 'string', 'max:150', 'unique:brands,slug'],
            'country' => ['nullable', 'string', 'max:100'],
            'parent_brand_id' => ['nullable', 'exists:brands,id'],
            'is_active' => ['nullable', 'boolean'],
            'created_by' => ['nullable', 'exists:users,id'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
