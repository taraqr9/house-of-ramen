<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('order-view');
    }

    public function view(User $user, Order $model): bool
    {
        return $user->can('order-view');
    }

    /**
     * Open a table/takeaway order and send item rounds to the kitchen.
     */
    public function create(User $user): bool
    {
        return $user->can('order-create');
    }

    public function addItems(User $user, Order $model): bool
    {
        return $user->can('order-create');
    }

    /**
     * Guest count / general note.
     */
    public function update(User $user, Order $model): bool
    {
        return $user->can('order-edit');
    }

    public function bill(User $user, Order $model): bool
    {
        return $user->can('billing-view');
    }

    public function discount(User $user, Order $model): bool
    {
        return $user->can('order-discount');
    }

    public function cancel(User $user, Order $model): bool
    {
        return $user->can('order-cancel');
    }

    public function complete(User $user, Order $model): bool
    {
        return $user->can('order-complete');
    }
}
