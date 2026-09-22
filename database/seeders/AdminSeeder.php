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
         * Reset permission cache again after assigning
         */
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
