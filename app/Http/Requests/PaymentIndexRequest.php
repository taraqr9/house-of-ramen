<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('payment-view');
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethodEnum::class)],
            'status' => ['nullable', Rule::enum(PaymentStatusEnum::class)],
            'received_by' => ['nullable', 'integer'],
            'keyword' => ['nullable', 'string', 'max:150'],
        ];
    }
}
