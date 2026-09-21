<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantReservationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_reservation-view');
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,cancelled'],
        ];
    }
}
