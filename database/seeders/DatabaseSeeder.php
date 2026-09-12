<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            MenuSeeder::class,
            RestaurantSeeder::class,
        ]);

        // Local-only convenience data for exercising the admin's user list
        // UI - never appropriate on a real production database.
        if (app()->environment('local')) {
            User::factory(10)->create();
        }
    }
}
