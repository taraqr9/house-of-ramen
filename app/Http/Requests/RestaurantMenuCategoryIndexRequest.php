<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantMenuCategoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_menu_category-view');
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
