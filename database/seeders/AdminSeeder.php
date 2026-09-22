<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
         * Reset permission cache
         */
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
         * Default CRUD actions
         */
        $actions = [
            'view',
            'create',
            'edit',
            'delete',
        ];

        /*
         * Manual permissions
         * These are not always generated from model names,
         * so we keep them explicitly.
         */
        $manualPermissions = [
            'dashboard-view',

            'role-view',
            'role-create',
            'role-edit',
            'role-delete',

            'user-view',
            'user-create',
            'user-edit',
            'user-delete',

            'activity_log-view',
            'error_log-view',
            'user-impersonate',
        ];

        foreach ($manualPermissions as $permission) {
            Permission::updateOrCreate(
                [
                    'name' => $permission,
                ],
                [
                    'guard_name' => 'web',
                ]
            );
        }

        /*
         * Create permissions from all models inside app/Models
         *
         * Example:
         * User model => user-view, user-create, user-edit, user-delete
         * SalaryReport model => salary_report-view, salary_report-create, etc.
         */
        $models = collect(File::files(app_path('Models')))
            ->map(function ($file) {
                return pathinfo($file->getFilename(), PATHINFO_FILENAME);
            })
            ->sort()
            ->values();

        foreach ($models as $modelName) {
            $moduleName = Str::snake($modelName);

            foreach ($actions as $action) {
                Permission::updateOrCreate(
                    [
                        'name' => $moduleName.'-'.$action,
                    ],
                    [
                        'guard_name' => 'web',
                    ]
                );
            }
        }

        /*
         * Create or update default admin user
         */
        $avatarPath = 'users/avatars/dummy_man.png';

        if (! Storage::disk('public')->exists($avatarPath)) {
            Storage::disk('public')->put(
                $avatarPath,
                file_get_contents(database_path('seed-data/users/dummy_man.png'))
            );
        }

        $admin = User::firstOrCreate(
            [
                'username' => 'admin',
            ],
            [
                'name' => 'Admin',
                'avatar_path' => $avatarPath,
                'email' => env('ADMIN_SEED_EMAIL', 'admin@example.com'),
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
            ]
        );

        $superAdminRole = Role::firstOrCreate(
            [
                'name' => 'Super Admin',
                'guard_name' => 'web',
            ],
        );

        $superAdminRole->syncPermissions(Permission::all());

        if (! $admin->hasRole($superAdminRole)) {
            $admin->assignRole($superAdminRole);
        }

        /*
         * Admin role - everything Super Admin can do except manage Roles
         * and view the Activity/Error logs. Super Admin itself stays the
         * only role that can see or touch Roles, logs, or other Super
         * Admin users (enforced in RolePolicy/UserPolicy, not here).
         */
        $adminExcludedPermissions = [
            'role-view',
            'role-create',
            'role-edit',
            'role-delete',
            'activity_log-view',
            'error_log-view',
        ];

        $adminRole = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $adminRole->syncPermissions(
            Permission::whereNotIn('name', $adminExcludedPermissions)->get()
        );

        /*
         * The two named Admin-role users the restaurant actually uses,
         * day to day - alongside the seeded Super Admin, these are the
         * only 3 users this seeder produces.
         */
        $additionalAdmins = [
            ['name' => 'Rafatun Binte Rahman', 'username' => 'rafatun-binte-rahman', 'email' => 'rafatun-binte-rahman@example.com'],
            ['name' => 'Tonmoy Singha', 'username' => 'tonmoy-singha', 'email' => 'tonmoy-singha@example.com'],
        ];

        foreach ($additionalAdmins as $userData) {
            $additionalAdmin = User::firstOrCreate(
                [
                    'username' => $userData['username'],
                ],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'remember_token' => Str::random(10),
                ]
            );

            if (! $additionalAdmin->hasRole($adminRole)) {
                $additionalAdmin->assignRole($adminRole);
            }
        }

        /*
         * Employee role - Dashboard plus the full Restaurant domain only
         * (menu, gallery, videos, popups, reviews, reservations). No
         * access to Users, Roles, Menus, or logs.
         */
        $restaurantResources = [
            'restaurant_menu_category',
            'restaurant_menu_item',
            'restaurant_menu_item_image',
            'restaurant_gallery_image',
            'restaurant_video_feature',
            'restaurant_popup_offer',
            'restaurant_review',
        ];

        $employeePermissionNames = collect(['dashboard-view', 'restaurant-view'])
            ->merge(
                collect($restaurantResources)->flatMap(
                    fn ($resource) => collect($actions)->map(fn ($action) => "{$resource}-{$action}")
                )
            )
            ->merge(['restaurant_reservation-view', 'restaurant_reservation-edit', 'restaurant_reservation-delete'])
            ->unique()
            ->values();

        $employeeRole = Role::firstOrCreate([
            'name' => 'Employee',
            'guard_name' => 'web',
        ]);

        $employeeRole->syncPermissions(
            Permission::whereIn('name', $employeePermissionNames)->get()
        );

        /*
         * Reset permission cache again after assigning
         */
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
