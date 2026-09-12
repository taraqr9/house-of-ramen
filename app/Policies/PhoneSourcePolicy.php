<?php

namespace App\Policies;

use App\Models\PhoneSource;
use App\Models\User;

class PhoneSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('phone_source-view');
    }

    public function view(User $user, PhoneSource $model): bool
    {
        return $user->can('phone_source-view');
    }

    public function update(User $user, PhoneSource $model): bool
    {
        return $user->can('phone_source-edit');
    }
}
