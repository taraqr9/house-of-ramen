<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantReservationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('restaurant_reservation-edit');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Staff only ever change the status here (pending -> confirmed/
     * cancelled after calling the customer) - the customer-submitted
     * details themselves aren't editable from the admin panel.
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,confirmed,cancelled'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
