<?php

namespace App\Policies;

use App\Models\PhoneDataConflict;
use App\Models\User;

class PhoneDataConflictPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('phone_data_conflict-view');
    }

    public function resolve(User $user, PhoneDataConflict $model): bool
    {
        return $user->can('phone_data_conflict-edit');
    }
}
