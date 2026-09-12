<?php

namespace App\Policies;

use App\Models\PhoneImportRun;
use App\Models\User;

class PhoneImportRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('phone_import_run-view');
    }

    public function view(User $user, PhoneImportRun $model): bool
    {
        return $user->can('phone_import_run-view');
    }
}
