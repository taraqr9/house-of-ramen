<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class RestaurantGalleryImageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_gallery_image-create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'restaurant_id' => $this->input('restaurant_id') ?: Restaurant::query()->value('id'),
            'is_active' => (int) $this->input('is_active', 1),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'exists:restaurants,id'],
            'category' => ['required', 'in:interior,exterior,food,event,other'],
            'caption' => ['nullable', 'string', 'max:200'],
            'image' => ['required', 'image', 'max:4096'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'created_by' => ['nullable', 'exists:users,id'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
