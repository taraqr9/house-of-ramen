<?php

namespace App\Http\Requests;

use App\Models\RestaurantVideoFeature;
use Illuminate\Foundation\Http\FormRequest;

class RestaurantVideoFeatureUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_video_feature-edit');
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
            'title' => ['required', 'string', 'max:150'],
            'video_url' => [
                'required',
                'url',
                'max:255',
                function ($attribute, $value, $fail): void {
                    if (! RestaurantVideoFeature::detectPlatform($value)) {
                        $fail('Enter a valid YouTube, Facebook, or Instagram video link.');
                    }
                },
            ],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
