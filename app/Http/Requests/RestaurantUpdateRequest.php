<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant-edit');
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
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:4000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:150'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'delivery_platforms' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
