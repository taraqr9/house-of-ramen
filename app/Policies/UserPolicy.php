<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user-view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('user-view') && $this->canSeeTarget($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('user-create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('user-edit') && $this->canSeeTarget($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('user-delete') && $user->id !== $model->id && $this->canSeeTarget($user, $model);
    }

    /**
     * Super Admin users are invisible to (and unmanageable by) everyone
     * except other Super Admins - a non-Super-Admin can't view, edit, or
     * delete a Super Admin account even by guessing its URL.
     */
    private function canSeeTarget(User $user, User $model): bool
    {
        return ! $model->hasRole('Super Admin') || $user->hasRole('Super Admin');
    }
}
