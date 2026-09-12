<?php

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds the default admin with the well-known "password" credential', function () {
    $this->seed(AdminSeeder::class);

    $admin = User::where('username', 'admin')->firstOrFail();

    expect(Hash::check('password', $admin->password))->toBeTrue();
});

it('is idempotent - reseeding an existing admin never resets their password', function () {
    $this->seed(AdminSeeder::class);
    $originalHash = User::where('username', 'admin')->firstOrFail()->password;

    $this->seed(AdminSeeder::class);

    expect(User::where('username', 'admin')->firstOrFail()->password)->toBe($originalHash);
});
