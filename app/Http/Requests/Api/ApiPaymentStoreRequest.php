<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\PaymentStoreRequest;

/**
 * Same rules as the web payment form, but app clients must send an
 * idempotency_key (a UUID per payment attempt, reused on retries).
 */
class ApiPaymentStoreRequest extends PaymentStoreRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);
    }
}
