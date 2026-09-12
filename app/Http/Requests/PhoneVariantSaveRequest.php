<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhoneVariantSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->isMethod('POST') ? 'phone_variant-create' : 'phone_variant-edit';

        return auth()->user()->can($ability);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_official_bd' => (bool) $this->input('is_official_bd'),
            'expandable_storage' => (bool) $this->input('expandable_storage'),
            'is_active' => (int) $this->input('is_active', 0),
            'updated_by' => auth()->id(),
            'created_by' => $this->isMethod('POST') ? auth()->id() : $this->input('created_by'),
        ]);
    }

    public function rules(): array
    {
        return [
            'ram_gb' => ['nullable', 'integer', 'min:0', 'max:64'],
            'storage_gb' => ['nullable', 'integer', 'min:0', 'max:8192'],
            'storage_type' => ['nullable', 'string', 'max:50'],
            'expandable_storage' => ['nullable', 'boolean'],
            'expandable_storage_max_gb' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'is_official_bd' => ['nullable', 'boolean'],
            'status' => ['required', 'string', 'in:available,discontinued'],
            'is_active' => ['nullable', 'boolean'],

            'official_bd_price' => ['nullable', 'numeric', 'min:0'],
            'unofficial_bd_price' => ['nullable', 'numeric', 'min:0'],
            'store_id' => ['nullable', 'exists:phone_stores,id'],
            'availability_status' => ['nullable', 'string', 'in:in_stock,out_of_stock,preorder,discontinued'],

            'updated_by' => ['nullable', 'exists:users,id'],
            'created_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
