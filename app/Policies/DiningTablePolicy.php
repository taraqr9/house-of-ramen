<?php

namespace App\Policies;

use App\Models\DiningTable;
use App\Models\User;

class DiningTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('dining_table-view');
    }

    public function view(User $user, DiningTable $model): bool
    {
        return $user->can('dining_table-view');
    }

    public function create(User $user): bool
    {
        return $user->can('dining_table-create');
    }

    public function update(User $user, DiningTable $model): bool
    {
        return $user->can('dining_table-edit');
    }

    public function delete(User $user, DiningTable $model): bool
    {
        return $user->can('dining_table-delete');
    }
}
