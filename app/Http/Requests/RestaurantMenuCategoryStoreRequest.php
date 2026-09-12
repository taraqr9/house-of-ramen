<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RestaurantMenuCategoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_menu_category-create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'restaurant_id' => $this->input('restaurant_id') ?: Restaurant::query()->value('id'),
            'slug' => Str::slug($this->input('name', '')),
            'is_active' => (int) $this->input('is_active', 0),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'exists:restaurants,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150', 'unique:restaurant_menu_categories,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'created_by' => ['nullable', 'exists:users,id'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
