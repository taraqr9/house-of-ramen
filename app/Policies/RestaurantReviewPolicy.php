<?php

namespace App\Policies;

use App\Models\RestaurantReview;
use App\Models\User;

class RestaurantReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('restaurant_review-view');
    }

    public function view(User $user, RestaurantReview $model): bool
    {
        return $user->can('restaurant_review-view');
    }

    public function create(User $user): bool
    {
        return $user->can('restaurant_review-create');
    }

    public function update(User $user, RestaurantReview $model): bool
    {
        return $user->can('restaurant_review-edit');
    }

    public function delete(User $user, RestaurantReview $model): bool
    {
        return $user->can('restaurant_review-delete');
    }
}
