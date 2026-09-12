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
        | ANALYTICS
        |--------------------------------------------------------------------------
        | The GA4-backed reporting page (App\Http\Controllers\AnalyticsController)
        | - reuses the same "dashboard-view" permission as Dashboard above rather
        | than a new one, since it's presenting the same class of data.
        */
        Menu::updateOrCreate(
            [
                'route' => 'analytics.index',
            ],
            [
                'title' => 'Analytics',
                'icon' => 'bx bx-bar-chart-alt-2',
                'permission' => 'dashboard-view',
                'serial' => 2,
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
                'serial' => 3,
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
                'serial' => 4,
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
                'serial' => 5,
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
                'serial' => 6,
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
        | PHONE DATA (PARENT)
        |--------------------------------------------------------------------------
        */
        $phoneData = Menu::updateOrCreate(
            [
                'title' => 'Phone Data',
                'parent_id' => null,
            ],
            [
                'icon' => 'bx bx-mobile-alt',
                'permission' => null,
                'serial' => 7,
                'route' => null,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'phone-data.dashboard',
            ],
            [
                'title' => 'Overview',
                'icon' => 'bx bx-grid-alt me-1',
                'parent_id' => $phoneData->id,
                'permission' => 'phone-view',
                'serial' => 1,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'phones.index',
            ],
            [
                'title' => 'Phones',
                'icon' => 'bx bx-devices me-1',
                'parent_id' => $phoneData->id,
                'permission' => 'phone-view',
                'serial' => 2,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'brands.index',
            ],
            [
                'title' => 'Brands',
                'icon' => 'bx bx-purchase-tag-alt me-1',
                'parent_id' => $phoneData->id,
                'permission' => 'brand-view',
                'serial' => 3,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'phone-stores.index',
            ],
            [
                'title' => 'Retailers & Stores',
                'icon' => 'bx bx-store me-1',
                'parent_id' => $phoneData->id,
                'permission' => 'phone_store-view',
                'serial' => 4,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'phone-sources.index',
            ],
            [
                'title' => 'Data Sources',
                'icon' => 'bx bx-git-branch me-1',
                'parent_id' => $phoneData->id,
                'permission' => 'phone_source-view',
                'serial' => 5,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'phone-import-runs.index',
            ],
            [
                'title' => 'Import Runs',
                'icon' => 'bx bx-import me-1',
                'parent_id' => $phoneData->id,
                'permission' => 'phone_import_run-view',
                'serial' => 6,
                'is_active' => 1,
            ]
        );

        Menu::updateOrCreate(
            [
                'route' => 'data-review.index',
            ],
            [
                'title' => 'Data Review',
                'icon' => 'bx bx-check-shield me-1',
                'parent_id' => $phoneData->id,
                'permission' => 'phone_data_review-view',
                'serial' => 7,
                'is_active' => 1,
            ]
        );
    }
}
