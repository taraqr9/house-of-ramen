<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiningTableUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('dining_table-edit');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => (int) $this->input('is_active', 0),
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            // Unique among live tables only - a soft-deleted "T5" must not
            // block re-creating T5.
            'name' => ['required', 'string', 'max:50', Rule::unique('dining_tables', 'name')->ignore($this->route('dining_table'))->withoutTrashed()],
            'area' => ['nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
