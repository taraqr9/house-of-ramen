<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The backend input contract for the future Vue/Inertia recommendation
 * questionnaire. Every field is optional except that at least providing
 * a budget is what makes the result useful - the engine still returns
 * something sensible with none of this filled in.
 */
class PhoneRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_budget' => ['nullable', 'integer', 'min:1000'],
            'primary_usage' => ['nullable', 'string', 'max:50'],

            'importance' => ['nullable', 'array'],
            'importance.*' => ['nullable', 'integer', 'min:1', 'max:5'],

            'preferred_brand_ids' => ['nullable', 'array'],
            'preferred_brand_ids.*' => ['integer', 'exists:brands,id'],

            'excluded_brand_ids' => ['nullable', 'array'],
            'excluded_brand_ids.*' => ['integer', 'exists:brands,id'],

            'price_preference' => ['nullable', 'in:official,unofficial,both'],
            'required_5g' => ['nullable', 'boolean'],
            'required_nfc' => ['nullable', 'boolean'],
            'required_storage_gb' => ['nullable', 'integer', 'min:0'],

            'result_count' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'importance.*.min' => 'Importance values use a 1 (not important) to 5 (very high) scale.',
            'importance.*.max' => 'Importance values use a 1 (not important) to 5 (very high) scale.',
        ];
    }
}
