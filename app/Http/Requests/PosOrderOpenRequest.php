<?php

namespace App\Http\Requests;

use App\Enums\OrderTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosOrderOpenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('order-create');
    }

    public function rules(): array
    {
        return [
            'order_type' => ['required', Rule::enum(OrderTypeEnum::class)],
            'dining_table_id' => ['nullable', 'required_if:order_type,'.OrderTypeEnum::DINE_IN->value, 'integer', 'exists:dining_tables,id'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'general_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
