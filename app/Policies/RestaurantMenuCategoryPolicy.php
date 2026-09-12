<?php

namespace App\Policies;

use App\Models\RestaurantMenuCategory;
use App\Models\User;

class RestaurantMenuCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('restaurant_menu_category-view');
    }

    public function view(User $user, RestaurantMenuCategory $model): bool
    {
        return $user->can('restaurant_menu_category-view');
    }

    public function create(User $user): bool
    {
        return $user->can('restaurant_menu_category-create');
    }

    public function update(User $user, RestaurantMenuCategory $model): bool
    {
        return $user->can('restaurant_menu_category-edit');
    }

    public function delete(User $user, RestaurantMenuCategory $model): bool
    {
        return $user->can('restaurant_menu_category-delete');
    }
}
