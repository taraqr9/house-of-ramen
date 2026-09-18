<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RestaurantMenuItemStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_menu_item-create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'restaurant_id' => $this->input('restaurant_id') ?: Restaurant::query()->value('id'),
            'slug' => Str::slug($this->input('name', '')),
            'is_featured' => (int) $this->input('is_featured', 0),
            'is_new' => (int) $this->input('is_new', 0),
            'is_available' => (int) $this->input('is_available', 1),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'exists:restaurants,id'],
            'restaurant_menu_category_id' => ['required', 'exists:restaurant_menu_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150', 'unique:restaurant_menu_items,slug'],
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
            'created_by' => ['nullable', 'exists:users,id'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
