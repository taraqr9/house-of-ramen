<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('role-view');
    }

    public function view(User $user, Role $model): bool
    {
        return $user->can('role-view');
    }

    public function create(User $user): bool
    {
        return $user->can('role-create');
    }

    public function update(User $user, Role $model): bool
    {
        return $user->can('role-edit');
    }

    public function delete(User $user, Role $model): bool
    {
        return $user->can('role-delete') && $model->name !== 'Super Admin';
    }
}
