<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantGalleryImageUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_gallery_image-edit');
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
            'category' => ['required', 'in:interior,exterior,food,event,other'],
            'caption' => ['nullable', 'string', 'max:200'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
