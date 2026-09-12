<?php

namespace App\Policies;

use App\Models\RestaurantGalleryImage;
use App\Models\User;

class RestaurantGalleryImagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('restaurant_gallery_image-view');
    }

    public function view(User $user, RestaurantGalleryImage $model): bool
    {
        return $user->can('restaurant_gallery_image-view');
    }

    public function create(User $user): bool
    {
        return $user->can('restaurant_gallery_image-create');
    }

    public function update(User $user, RestaurantGalleryImage $model): bool
    {
        return $user->can('restaurant_gallery_image-edit');
    }

    public function delete(User $user, RestaurantGalleryImage $model): bool
    {
        return $user->can('restaurant_gallery_image-delete');
    }
}
