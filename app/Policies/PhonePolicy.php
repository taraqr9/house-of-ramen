<?php

namespace App\Policies;

use App\Models\Phone;
use App\Models\User;

class PhonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('phone-view');
    }

    public function view(User $user, Phone $model): bool
    {
        return $user->can('phone-view');
    }

    public function create(User $user): bool
    {
        return $user->can('phone-create');
    }

    public function update(User $user, Phone $model): bool
    {
        return $user->can('phone-edit');
    }

    public function delete(User $user, Phone $model): bool
    {
        return $user->can('phone-delete');
    }
}
