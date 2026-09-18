<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use App\Models\RestaurantVideoFeature;
use Illuminate\Foundation\Http\FormRequest;

class RestaurantVideoFeatureStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_video_feature-create');
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
            // Only really needed for Facebook/Instagram, which have no
            // public thumbnail endpoint the way YouTube does - optional
            // for every platform so a YouTube link can still auto-derive
            // its own thumbnail without one.
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'created_by' => ['nullable', 'exists:users,id'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
