<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosOrderCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('order-cancel');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
