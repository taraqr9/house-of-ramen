<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PhoneStoreSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->isMethod('POST') ? 'phone_store-create' : 'phone_store-edit';

        return auth()->user()->can($ability);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('name', '')),
            'is_active' => (int) $this->input('is_active', 0),
            'updated_by' => auth()->id(),
            'created_by' => $this->isMethod('POST') ? auth()->id() : $this->input('created_by'),
        ]);
    }

    public function rules(): array
    {
        $storeId = $this->route('phone_store')?->id;

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('phone_stores', 'name')->ignore($storeId)],
            'slug' => ['required', 'string', 'max:150', Rule::unique('phone_stores', 'slug')->ignore($storeId)],
            'website_url' => ['nullable', 'url', 'max:255'],
            'type' => ['required', 'string', 'in:official,authorized,marketplace,other'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
