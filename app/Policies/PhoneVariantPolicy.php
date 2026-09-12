<?php

namespace App\Policies;

use App\Models\PhoneVariant;
use App\Models\User;

class PhoneVariantPolicy
{
    public function create(User $user): bool
    {
        return $user->can('phone_variant-create');
    }

    public function update(User $user, PhoneVariant $model): bool
    {
        return $user->can('phone_variant-edit');
    }

    public function delete(User $user, PhoneVariant $model): bool
    {
        return $user->can('phone_variant-delete');
    }
}
