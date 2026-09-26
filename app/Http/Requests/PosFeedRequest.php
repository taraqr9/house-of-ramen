<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Polling cursor for the Kitchen / Ready to Serve feeds. Authorization is
 * per screen, done in the controller.
 */
class PosFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
        ];
    }
}
