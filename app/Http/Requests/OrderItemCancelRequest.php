<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderItemCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('order_item-cancel');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
