<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\PosOrderItemsStoreRequest;

/**
 * Same rules as the web cart, but app clients must send a submission_key
 * (a UUID per "send round" action, reused on retries).
 */
class ApiAddRoundRequest extends PosOrderItemsStoreRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'submission_key' => ['required', 'string', 'max:64'],
        ]);
    }
}
