<?php

namespace App\Policies;

use App\Models\RestaurantPopupOffer;
use App\Models\User;

class RestaurantPopupOfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('restaurant_popup_offer-view');
    }

    public function view(User $user, RestaurantPopupOffer $model): bool
    {
        return $user->can('restaurant_popup_offer-view');
    }

    public function create(User $user): bool
    {
        return $user->can('restaurant_popup_offer-create');
    }

    public function update(User $user, RestaurantPopupOffer $model): bool
    {
        return $user->can('restaurant_popup_offer-edit');
    }

    public function delete(User $user, RestaurantPopupOffer $model): bool
    {
        return $user->can('restaurant_popup_offer-delete');
    }
}
