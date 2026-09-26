<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiningTableIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('dining_table-view');
    }

    public function rules(): array
    {
        return [
            'area' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'occupancy' => ['nullable', Rule::in(['occupied', 'available'])],
            'keyword' => ['nullable', 'string', 'max:150'],
        ];
    }
}
