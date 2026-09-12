<?php

use App\Models\Phone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->directory = storage_path('framework/testing/catalogue-master-seed-'.uniqid());
    File::ensureDirectoryExists("{$this->directory}/images/seed-phone-a");
    config(['phone_catalogue_snapshot.directory' => $this->directory]);
    Storage::fake('public');

    File::put("{$this->directory}/brands.json", json_encode(['brands' => [
        ['slug' => 'seedbrand', 'name' => 'SeedBrand', 'country' => 'BD', 'is_active' => true],
    ]]));

    File::put("{$this->directory}/stores.json", json_encode(['stores' => [
        ['slug' => 'seed-store', 'name' => 'Seed Store', 'type' => 'online', 'is_active' => true],
    ]]));

    File::put("{$this->directory}/images/seed-phone-a/0-1.webp", 'fake-bytes');

    File::put("{$this->directory}/seedbrand.json", json_encode(['brand_slug' => 'seedbrand', 'phones' => [
        [
            'slug' => 'seed-phone-a',
            'name' => 'Seed Phone A',
            'status' => 'available',
            'is_active' => true,
            'identity_confidence' => 90,
            'spec' => ['processor' => 'Test Chip', 'battery_capacity_mah' => 5000],
            'variants' => [[
                'slug' => 'seed-phone-a-8-128',
                'ram_gb' => 8,
                'storage_gb' => 128,
                'is_active' => true,
                'prices' => [[
                    'store_slug' => 'seed-store', 'price_type' => 'unofficial_bd', 'amount' => '29999.00', 'currency' => 'BDT',
                ]],
            ]],
            'images' => [[
                'seed_file' => 'seed-phone-a/0-1.webp',
                'is_primary' => true,
                'sort_order' => 0,
                'status' => 'verified',
                'match_confidence' => 100,
            ]],
            'reviews' => [],
        ],
    ]]));
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

it('runs db:seed and rebuilds the full catalogue from the offline snapshot in one command', function () {
    $this->artisan('phonekinbo:seed', ['--force' => true])->assertExitCode(0);

    $phone = Phone::where('slug', 'seed-phone-a')->first();

    expect($phone)->not->toBeNull();
    expect($phone->is_active)->toBeTrue();
    expect(User::where('username', 'admin')->exists())->toBeTrue();
});

it('prints a summary report table after seeding', function () {
    $this->artisan('phonekinbo:seed', ['--force' => true])
        ->expectsOutputToContain('Seed complete. Final catalogue state:')
        ->assertExitCode(0);
});

it('is idempotent - running it twice does not duplicate the catalogue', function () {
    $this->artisan('phonekinbo:seed', ['--force' => true])->assertExitCode(0);
    $this->artisan('phonekinbo:seed', ['--force' => true])->assertExitCode(0);

    expect(Phone::where('slug', 'seed-phone-a')->count())->toBe(1);
});
