<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantMenuItemIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_menu_item-view');
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:150'],
            'restaurant_menu_category_id' => ['nullable', 'exists:restaurant_menu_categories,id'],
            'is_available' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
        ];
    }
}
