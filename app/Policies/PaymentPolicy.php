<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payment-view');
    }

    public function view(User $user, Payment $model): bool
    {
        return $user->can('payment-view');
    }

    /**
     * Receive a payment against an order.
     */
    public function create(User $user): bool
    {
        return $user->can('payment-create');
    }

    /**
     * Payments are never deleted - "delete" permission voids one.
     */
    public function void(User $user, Payment $model): bool
    {
        return $user->can('payment-delete');
    }
}
