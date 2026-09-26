<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentVoidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('payment-delete');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
