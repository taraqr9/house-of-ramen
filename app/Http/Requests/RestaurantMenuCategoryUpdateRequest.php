<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantMenuCategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_menu_category-edit');
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
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
