<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('pos_report-view');
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
