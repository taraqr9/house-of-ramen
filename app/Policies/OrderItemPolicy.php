<?php

namespace App\Policies;

use App\Enums\OrderItemStatusEnum;
use App\Models\OrderItem;
use App\Models\User;

class OrderItemPolicy
{
    /**
     * Kitchen moves (pending → preparing → ready) and the serving move
     * (ready → served) are separate jobs, so separate permissions.
     */
    public function transition(User $user, OrderItem $model, OrderItemStatusEnum $to): bool
    {
        return $to === OrderItemStatusEnum::SERVED
            ? $user->can('serving-update')
            : $user->can('kitchen-update');
    }

    public function acknowledge(User $user, OrderItem $model): bool
    {
        return $user->can('serving-update');
    }

    public function cancel(User $user, OrderItem $model): bool
    {
        return $user->can('order_item-cancel');
    }
}
