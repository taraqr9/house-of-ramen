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
        | USERS
        |--------------------------------------------------------------------------
        */
        Menu::updateOrCreate(
            [
                'route' => 'users.index',
            ],
            [
                'title' => 'Users',
                'icon' => 'bx bx-user-check',
                'permission' => 'user-view',
                'serial' => 2,
                'parent_id' => null,
                'is_active' => 1,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ROLE
        |--------------------------------------------------------------------------
        */
        Menu::updateOrCreate(
            [
                'route' => 'roles.index',
            ],
            [
                'title' => 'Role',
                'icon' => 'bx bx-shield-quarter',
                'permission' => 'role-view',
                'serial' => 3,
                'parent_id' => null,
                'is_active' => 1,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | MENUS
        |--------------------------------------------------------------------------
        */
        Menu::updateOrCreate(
            [
                'route' => 'menus.index',
            ],
            [
                'title' => 'Menus',
                'icon' => 'bx bx-menu',
                'permission' => 'menu-view',
                'serial' => 4,
                'parent_id' => null,
                'is_active' => 1,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | LOGS (PARENT)
        |--------------------------------------------------------------------------
        */
        $logs = Menu::updateOrCreate(
            [
                'title' => 'Logs',
                'parent_id' => null,
            ],
            [
                'icon' => 'bx bx-history',
                'permission' => null,
                'serial' => 5,
                'route' => null,
                'is_active' => 1,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | CHILD: ACTIVITY LOGS
        |--------------------------------------------------------------------------
        */
        Menu::updateOrCreate(
            [
                'route' => 'logs.activity',
            ],
            [
                'title' => 'Activity Logs',
                'icon' => 'bx bx-list-ul me-1',
                'parent_id' => $logs->id,
                'permission' => 'activity_log-view',
                'serial' => 1,
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
                'parent_id' => $logs->id,
                'permission' => 'error_log-view',
                'serial' => 2,
                'is_active' => 1,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | RESTAURANT (PARENT)
        |--------------------------------------------------------------------------
        */
        $restaurant = Menu::updateOrCreate(
            [
                'title' => 'Restaurant',
                'parent_id' => null,
            ],
            [
                'icon' => 'bx bx-restaurant',
                'permission' => null,
                'serial' => 6,
                'route' => null,
                'is_active' => 1,
            ]
        );

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
    }
}
