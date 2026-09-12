<?php

namespace App\Policies;

use App\Models\PhoneDataReview;
use App\Models\User;

class PhoneDataReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('phone_data_review-view');
    }

    public function review(User $user, PhoneDataReview $model): bool
    {
        return $user->can('phone_data_review-edit');
    }
}
