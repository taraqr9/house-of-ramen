<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One kitchen round. Only ids/quantities/notes come from the browser -
 * names and prices are always read from the menu server-side.
 */
class PosOrderItemsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('order-create');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'submission_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
