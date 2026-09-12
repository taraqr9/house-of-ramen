<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhoneSourceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('phone_source-edit');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => (int) $this->input('is_active', 0),
            'requires_review' => (int) $this->input('requires_review', 0),
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'reliability_score' => ['required', 'integer', 'min:0', 'max:100'],
            'requires_review' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
