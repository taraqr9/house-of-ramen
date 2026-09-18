<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantPopupOfferIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_popup_offer-view');
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
