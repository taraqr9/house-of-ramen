<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | DASHBOARD
        |--------------------------------------------------------------------------
        */
        Menu::updateOrCreate(
            [
                'route' => 'dashboard',
            ],
            [
                'title' => 'Dashboard',
                'icon' => 'bx bx-home-circle',
                'permission' => 'dashboard-view',
                'serial' => 1,
                'parent_id' => null,
                'is_active' => 1,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | POS OPERATIONS (PARENT)
        |--------------------------------------------------------------------------
        */
        $pos = $this->parentMenu('POS Operations', 'bx bx-receipt', 4);

        /*
         * Placeholders only - none of these modules exist yet. They carry no
         * route and stay inactive (hidden from the sidebar) until each module
         * is built; at that point set its route and flip is_active on. The
         * permission names are the ones AdminSeeder will generate from the
         * future models (e.g. DiningTable => dining_table-view).
         */
        $posChildren = [
            ['title' => 'Tables', 'icon' => 'bx bx-grid-alt me-1', 'permission' => 'dining_table-view'],
            ['title' => 'New Order / Terminal', 'icon' => 'bx bx-plus-circle me-1', 'permission' => 'order-create'],
            ['title' => 'Active Orders', 'icon' => 'bx bx-list-check me-1', 'permission' => 'order-view'],
            ['title' => 'Kitchen', 'icon' => 'bx bx-dish me-1', 'permission' => 'order_item-edit'],
            ['title' => 'Ready to Serve', 'icon' => 'bx bx-bell me-1', 'permission' => 'order_item-view'],
            ['title' => 'Billing', 'icon' => 'bx bx-receipt me-1', 'permission' => 'order-edit'],
            ['title' => 'Payments', 'icon' => 'bx bx-credit-card me-1', 'permission' => 'payment-view'],
            ['title' => 'Completed Orders', 'icon' => 'bx bx-check-double me-1', 'permission' => 'order-view'],
            ['title' => 'Reports', 'icon' => 'bx bx-bar-chart-alt-2 me-1', 'permission' => 'order-view'],
        ];

        foreach ($posChildren as $index => $child) {
            $existing = Menu::query()
                ->where('parent_id', $pos->id)
                ->where('title', $child['title'])
                ->first();

            /*
             * Only create the placeholder - once a module is built and its
             * menu gets a route / is switched on, re-running the seeder must
             * not reset it back to an inactive placeholder.
             */
            if (! $existing) {
                Menu::create([
                    'title' => $child['title'],
                    'icon' => $child['icon'],
                    'route' => null,
                    'parent_id' => $pos->id,
                    'permission' => $child['permission'],
                    'serial' => $index + 1,
                    'is_active' => 0,
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | RESTAURANT SETTINGS (PARENT)
        |--------------------------------------------------------------------------
        */
        $restaurant = $this->parentMenu('Restaurant Settings', 'bx bx-restaurant', 3, 'Restaurant');

        Menu::updateOrCreate(
            [
                'route' => 'restaurant.edit',
            ],
            [
                'title' => 'Settings',
                'icon' => 'bx bx-cog me-1',
                'parent_id' => $restaurant->id,
                'permission' => 'restaurant-view',
                'serial' => 1,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'restaurant-menu-categories.index',
            ],
            [
                'title' => 'Menu Categories',
                'icon' => 'bx bx-collection me-1',
                'parent_id' => $restaurant->id,
                'permission' => 'restaurant_menu_category-view',
                'serial' => 2,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'restaurant-menu-items.index',
            ],
            [
                'title' => 'Menu Items',
                'icon' => 'bx bx-food-menu me-1',
                'parent_id' => $restaurant->id,
                'permission' => 'restaurant_menu_item-view',
                'serial' => 3,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'restaurant-gallery-images.index',
            ],
            [
                'title' => 'Gallery',
                'icon' => 'bx bx-images me-1',
                'parent_id' => $restaurant->id,
                'permission' => 'restaurant_gallery_image-view',
                'serial' => 4,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'restaurant-video-features.index',
            ],
            [
                'title' => 'Video Features',
                'icon' => 'bx bxl-youtube me-1',
                'parent_id' => $restaurant->id,
                'permission' => 'restaurant_video_feature-view',
                'serial' => 5,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'restaurant-popup-offers.index',
            ],
            [
                'title' => 'Popup Offers',
                'icon' => 'bx bx-purchase-tag-alt me-1',
                'parent_id' => $restaurant->id,
                'permission' => 'restaurant_popup_offer-view',
                'serial' => 6,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'restaurant-reviews.index',
            ],
            [
                'title' => 'Reviews',
                'icon' => 'bx bx-star me-1',
                'parent_id' => $restaurant->id,
                'permission' => 'restaurant_review-view',
                'serial' => 7,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'restaurant-reservations.index',
            ],
            [
                'title' => 'Reservations',
                'icon' => 'bx bx-calendar-check me-1',
                'parent_id' => $restaurant->id,
                'permission' => 'restaurant_reservation-view',
                'serial' => 8,
                'is_active' => 1,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | SYSTEM ADMINISTRATION (PARENT)
        |--------------------------------------------------------------------------
        | Replaces the old top-level Users/Role/Menus items and the "Logs"
        | group - the sidebar only renders two levels, so the log pages sit
        | directly under this parent instead of a nested Logs group.
        */
        $systemAdministration = $this->parentMenu('System Administration', 'bx bx-cog', 2, 'Logs');

        Menu::updateOrCreate(
            [
                'route' => 'users.index',
            ],
            [
                'title' => 'Users',
                'icon' => 'bx bx-user-check me-1',
                'permission' => 'user-view',
                'serial' => 1,
                'parent_id' => $systemAdministration->id,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'roles.index',
            ],
            [
                'title' => 'Roles & Permissions',
                'icon' => 'bx bx-shield-quarter me-1',
                'permission' => 'role-view',
                'serial' => 2,
                'parent_id' => $systemAdministration->id,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'menus.index',
            ],
            [
                'title' => 'Navigation / Menus',
                'icon' => 'bx bx-menu me-1',
                'permission' => 'menu-view',
                'serial' => 3,
                'parent_id' => $systemAdministration->id,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'logs.activity',
            ],
            [
                'title' => 'Activity Logs',
                'icon' => 'bx bx-list-ul me-1',
                'parent_id' => $systemAdministration->id,
                'permission' => 'activity_log-view',
                'serial' => 4,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'logs.error',
            ],
            [
                'title' => 'Error Logs',
                'icon' => 'bx bx-error-circle me-1',
                'parent_id' => $systemAdministration->id,
                'permission' => 'error_log-view',
                'serial' => 5,
                'is_active' => 1,
            ]
        );
    }

    /**
     * Create or update a top-level group menu (no route, no permission - the
     * sidebar hides it automatically when the user can see none of its
     * children). When $legacyTitle is given and the group doesn't exist yet,
     * the old top-level row is renamed in place instead of adding a duplicate.
     */
    private function parentMenu(string $title, string $icon, int $serial, ?string $legacyTitle = null): Menu
    {
        if ($legacyTitle !== null) {
            $exists = Menu::query()
                ->whereNull('parent_id')
                ->where('title', $title)
                ->exists();

            if (! $exists) {
                Menu::query()
                    ->whereNull('parent_id')
                    ->whereNull('route')
                    ->where('title', $legacyTitle)
                    ->first()
                    ?->update(['title' => $title]);
            }
        }

        return Menu::updateOrCreate(
            [
                'title' => $title,
                'parent_id' => null,
            ],
            [
                'icon' => $icon,
                'permission' => null,
                'serial' => $serial,
                'route' => null,
                'is_active' => 1,
            ]
        );
    }
}
