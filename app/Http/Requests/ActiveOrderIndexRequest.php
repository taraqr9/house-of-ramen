<?php

namespace App\Http\Requests;

use App\Enums\OrderStatusEnum;
use App\Enums\OrderTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActiveOrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('order-view');
    }

    public function rules(): array
    {
        return [
            'order_type' => ['nullable', Rule::enum(OrderTypeEnum::class)],
            'status' => ['nullable', Rule::in(OrderStatusEnum::activeValues())],
            'dining_table_id' => ['nullable', 'integer'],
            'keyword' => ['nullable', 'string', 'max:150'],
        ];
    }
}
