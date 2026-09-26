<?php

namespace App\Services\Pos;

use App\Models\RestaurantMenuItem;
use App\Models\User;

/**
 * Takes a dish off the menu (POS terminal and public site) from the
 * kitchen. Deliberately separate from cancelling an order item: the kitchen
 * chooses to do it, it never happens as a side effect of a cancellation.
 * Turning it back on stays in Restaurant Settings → Menu Items.
 */
class MenuAvailabilityService
{
    public function markUnavailable(RestaurantMenuItem $menuItem, User $user): RestaurantMenuItem
    {
        if ($menuItem->is_available) {
            $menuItem->update(['is_available' => false, 'updated_by' => $user->id]);
        }

        return $menuItem;
    }
}
