<?php

namespace App\Policies;

use App\Models\RestaurantVideoFeature;
use App\Models\User;

class RestaurantVideoFeaturePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('restaurant_video_feature-view');
    }

    public function view(User $user, RestaurantVideoFeature $model): bool
    {
        return $user->can('restaurant_video_feature-view');
    }

    public function create(User $user): bool
    {
        return $user->can('restaurant_video_feature-create');
    }

    public function update(User $user, RestaurantVideoFeature $model): bool
    {
        return $user->can('restaurant_video_feature-edit');
    }

    public function delete(User $user, RestaurantVideoFeature $model): bool
    {
        return $user->can('restaurant_video_feature-delete');
    }
}
