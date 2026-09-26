<?php

namespace App\Http\Requests;

use App\Enums\OrderItemStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KitchenStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('kitchen-update');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([OrderItemStatusEnum::PREPARING->value, OrderItemStatusEnum::READY->value])],
        ];
    }
}
