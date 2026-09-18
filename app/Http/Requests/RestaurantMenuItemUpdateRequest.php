<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantMenuItemUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_menu_item-edit');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_featured' => (int) $this->input('is_featured', 0),
            'is_new' => (int) $this->input('is_new', 0),
            'is_available' => (int) $this->input('is_available', 0),
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'restaurant_menu_category_id' => ['required', 'exists:restaurant_menu_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'price_note' => ['nullable', 'string', 'max:150'],
            'image' => ['nullable', 'image', 'max:4096'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'max:4096'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
