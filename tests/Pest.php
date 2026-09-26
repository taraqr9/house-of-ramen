<?php

use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A regular user granted exactly the given permissions - used across the
 * admin/permission feature tests.
 */
function adminUser(array $permissions = []): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * The one active Restaurant row every public page requires (see
 * App\Support\RestaurantPresenter and the Public\* controllers) - tests
 * hitting a public route need this to exist first, the same way real
 * usage depends on RestaurantSeeder having run.
 */
function makeRestaurant(array $attributes = []): Restaurant
{
    return Restaurant::factory()->create($attributes);
}

/**
 * Every POS permission - the "full access" POS user for flow tests.
 */
function posPermissions(): array
{
    return [
        'dining_table-view', 'dining_table-create', 'dining_table-edit', 'dining_table-delete',
        'order-view', 'order-create', 'order-edit', 'order-discount', 'order-cancel', 'order-complete',
        'order_item-cancel', 'kitchen-view', 'kitchen-update', 'kitchen-cancel', 'serving-view', 'serving-update',
        'billing-view', 'payment-view', 'payment-create', 'payment-delete', 'pos_report-view',
    ];
}

function posUser(?array $permissions = null): User
{
    return adminUser($permissions ?? posPermissions());
}

/**
 * The single restaurant + one active category, with the given VAT/service
 * charge percentages.
 */
function posSetup(float $vat = 0, float $serviceCharge = 0): RestaurantMenuCategory
{
    $restaurant = makeRestaurant(['vat_percent' => $vat, 'service_charge_percent' => $serviceCharge]);

    return RestaurantMenuCategory::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Ramen', 'is_active' => true]);
}

function posMenuItem(RestaurantMenuCategory $category, float $price, array $attributes = []): RestaurantMenuItem
{
    return RestaurantMenuItem::factory()->create(array_merge([
        'restaurant_id' => $category->restaurant_id,
        'restaurant_menu_category_id' => $category->id,
        'price' => $price,
        'is_available' => true,
    ], $attributes));
}

function posTable(string $name = 'T1'): DiningTable
{
    return DiningTable::factory()->create(['name' => $name]);
}
