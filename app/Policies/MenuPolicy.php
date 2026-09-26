<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('menu-view');
    }

    public function view(User $user, Menu $model): bool
    {
        return $user->can('menu-view');
    }

    public function create(User $user): bool
    {
        return $user->can('menu-create');
    }

    public function update(User $user, Menu $model): bool
    {
        return $user->can('menu-edit');
    }

    public function delete(User $user, Menu $model): bool
    {
        return $user->can('menu-delete');
    }
}
