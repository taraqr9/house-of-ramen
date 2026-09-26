<?php

namespace App\Http\Requests;

use App\Enums\KitchenCancelReasonEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KitchenItemCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('kitchen-cancel');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(KitchenCancelReasonEnum::class)],
            'reason_detail' => ['nullable', 'required_if:reason,'.KitchenCancelReasonEnum::OTHER->value, 'string', 'max:200'],
        ];
    }
}
