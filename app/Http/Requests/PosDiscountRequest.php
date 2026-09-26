<?php

namespace App\Http\Requests;

use App\Enums\DiscountTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('order-discount');
    }

    public function rules(): array
    {
        return [
            'discount_type' => ['required', Rule::enum(DiscountTypeEnum::class)],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }
}
