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
         * Each POS screen's menu. Rows were first seeded as route-less
         * placeholders, so match on route first and fall back to the
         * placeholder's title - updating it in place instead of adding a
         * duplicate. active_routes keeps the right item highlighted on the
         * screens reached from it (order screen, bill, print...).
         */
        $posChildren = [
            ['title' => 'Tables', 'route' => 'dining-tables.index', 'icon' => 'bx bx-grid-alt me-1', 'permission' => 'dining_table-view', 'active_routes' => null],
            ['title' => 'New Order / Terminal', 'route' => 'pos-terminal.index', 'icon' => 'bx bx-plus-circle me-1', 'permission' => 'order-create', 'active_routes' => ['pos-terminal.*', 'pos-orders.show']],
            ['title' => 'Active Orders', 'route' => 'pos-orders.active', 'icon' => 'bx bx-list-check me-1', 'permission' => 'order-view', 'active_routes' => ['pos-orders.active']],
            ['title' => 'Kitchen', 'route' => 'pos-kitchen.index', 'icon' => 'bx bx-dish me-1', 'permission' => 'kitchen-view', 'active_routes' => null],
            ['title' => 'Ready to Serve', 'route' => 'pos-serving.index', 'icon' => 'bx bx-bell me-1', 'permission' => 'serving-view', 'active_routes' => null],
            ['title' => 'Billing', 'route' => 'pos-billing.index', 'icon' => 'bx bx-receipt me-1', 'permission' => 'billing-view', 'active_routes' => ['pos-billing.*']],
            ['title' => 'Payments', 'route' => 'pos-payments.index', 'icon' => 'bx bx-credit-card me-1', 'permission' => 'payment-view', 'active_routes' => null],
            ['title' => 'Completed Orders', 'route' => 'pos-orders.completed', 'icon' => 'bx bx-check-double me-1', 'permission' => 'order-view', 'active_routes' => ['pos-orders.completed']],
            ['title' => 'Reports', 'route' => 'pos-reports.index', 'icon' => 'bx bx-bar-chart-alt-2 me-1', 'permission' => 'pos_report-view', 'active_routes' => null],
        ];

        foreach ($posChildren as $index => $child) {
            $menu = Menu::query()->where('route', $child['route'])->first()
                ?? Menu::query()->where('parent_id', $pos->id)->where('title', $child['title'])->first()
                ?? new Menu;

            $menu->fill([
                'title' => $child['title'],
                'icon' => $child['icon'],
                'route' => $child['route'],
                'parent_id' => $pos->id,
                'permission' => $child['permission'],
                'active_routes' => $child['active_routes'],
                'serial' => $index + 1,
                'is_active' => 1,
            ])->save();
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
