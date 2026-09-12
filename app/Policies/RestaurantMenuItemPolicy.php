<?php

namespace App\Policies;

use App\Models\RestaurantMenuItem;
use App\Models\User;

class RestaurantMenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('restaurant_menu_item-view');
    }

    public function view(User $user, RestaurantMenuItem $model): bool
    {
        return $user->can('restaurant_menu_item-view');
    }

    public function create(User $user): bool
    {
        return $user->can('restaurant_menu_item-create');
    }

    public function update(User $user, RestaurantMenuItem $model): bool
    {
        return $user->can('restaurant_menu_item-edit');
    }

    public function delete(User $user, RestaurantMenuItem $model): bool
    {
        return $user->can('restaurant_menu_item-delete');
    }
}
