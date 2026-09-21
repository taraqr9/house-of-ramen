<?php

namespace App\Policies;

use App\Models\RestaurantReservation;
use App\Models\User;

class RestaurantReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('restaurant_reservation-view');
    }

    public function view(User $user, RestaurantReservation $model): bool
    {
        return $user->can('restaurant_reservation-view');
    }

    public function update(User $user, RestaurantReservation $model): bool
    {
        return $user->can('restaurant_reservation-edit');
    }

    public function delete(User $user, RestaurantReservation $model): bool
    {
        return $user->can('restaurant_reservation-delete');
    }
}
