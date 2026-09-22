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
        return $user->can('role-view') && $this->canSeeTarget($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('role-create');
    }

    public function update(User $user, Role $model): bool
    {
        return $user->can('role-edit') && $this->canSeeTarget($user, $model);
    }

    public function delete(User $user, Role $model): bool
    {
        return $user->can('role-delete') && $model->name !== 'Super Admin';
    }

    /**
     * The Super Admin role itself is invisible to (and unmanageable by)
     * everyone except Super Admins - a non-Super-Admin can't view or edit
     * it even by guessing its URL.
     */
    private function canSeeTarget(User $user, Role $model): bool
    {
        return $model->name !== 'Super Admin' || $user->hasRole('Super Admin');
    }
}
