<?php

namespace App\Policies;

use App\Models\PhoneStore;
use App\Models\User;

class PhoneStorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('phone_store-view');
    }

    public function view(User $user, PhoneStore $model): bool
    {
        return $user->can('phone_store-view');
    }

    public function create(User $user): bool
    {
        return $user->can('phone_store-create');
    }

    public function update(User $user, PhoneStore $model): bool
    {
        return $user->can('phone_store-edit');
    }

    public function delete(User $user, PhoneStore $model): bool
    {
        return $user->can('phone_store-delete');
    }
}
