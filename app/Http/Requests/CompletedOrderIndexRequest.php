<?php

namespace App\Http\Requests;

use App\Enums\OrderStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompletedOrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('order-view');
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in([OrderStatusEnum::COMPLETED->value, OrderStatusEnum::CANCELLED->value])],
            'dining_table_id' => ['nullable', 'integer'],
            'order_number' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'mixed'])],
            'completed_by' => ['nullable', 'integer'],
            'keyword' => ['nullable', 'string', 'max:150'],
        ];
    }
}
