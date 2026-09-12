<?php

use App\Models\Phone;
use App\Models\PhoneImage;
use App\Models\PhonePrice;
use App\Models\PhoneSpec;
use App\Models\PhoneVariant;
use App\Models\User;
use App\Services\Analytics\Contracts\AnalyticsReportClient;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\Analytics\FakeAnalyticsReportClient;
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

function something()
{
    // ..
}

/**
 * A regular user granted exactly the given permissions - used across the
 * phone data admin/permission feature tests.
 */
function phoneDataUser(array $permissions = []): User
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
 * Composes a phone + spec + one variant + one price + a verified primary
 * image in a single call - the shape almost every recommendation/public-site
 * test needs, since Phone::scopePubliclyVisible() now requires a verified
 * image alongside is_active. Pass null for $specAttrs to leave the phone
 * without a spec row (missing-data scenarios). Pass withImage: false for a
 * test that manages its own PhoneImage rows (e.g. asserting exactly which
 * image state makes a phone visible) - attaching this helper's default
 * image too would leave two "verified primary" images fighting over which
 * one wins.
 *
 * The recommendation engine reads phone_market_prices (the outlier-
 * resistant current market price), never raw phone_prices directly - see
 * App\Services\PhoneImport\PriceAggregator - so this runs the real
 * recalculation after creating the price, the same as the import
 * pipeline and the admin variant form both do.
 */
function makeRecommendablePhone(
    array $phoneAttrs = [],
    ?array $specAttrs = [],
    array $variantAttrs = [],
    array $priceAttrs = [],
    bool $withImage = true,
): Phone {
    $phone = Phone::factory()->create($phoneAttrs);

    if ($specAttrs !== null) {
        PhoneSpec::factory()->create(array_merge(['phone_id' => $phone->id], $specAttrs));
    }

    $variant = PhoneVariant::factory()->create(array_merge(['phone_id' => $phone->id], $variantAttrs));
    $price = PhonePrice::factory()->create(array_merge(['phone_variant_id' => $variant->id], $priceAttrs));

    app(PriceAggregator::class)->recalculate($variant, $price->price_type);

    if ($withImage) {
        PhoneImage::factory()->for($phone)->create();
    }

    return $phone->fresh(['spec', 'variants.prices', 'variants.marketPrices', 'brand', 'images']);
}

/**
 * Binds a FakeAnalyticsReportClient over the AnalyticsReportClient
 * interface (see App\Services\Analytics\AnalyticsDashboardService) so a
 * test controls exactly what GA4 "returns" without ever making a real
 * network call - used by both the /admin dashboard's summary card and the
 * /admin/analytics page tests.
 */
function bindFakeAnalytics(array $responses = []): FakeAnalyticsReportClient
{
    $fake = new FakeAnalyticsReportClient($responses);
    app()->instance(AnalyticsReportClient::class, $fake);

    return $fake;
}

/**
 * A full, realistic set of canned GA4 responses covering every report
 * AnalyticsDashboardService issues (see its snapshot()) - reused by the
 * "happy path" tests in tests/Feature/Analytics/*.
 */
function fullAnalyticsResponses(): array
{
    return [
        '|' => [[
            'activeUsers' => '120', 'newUsers' => '40', 'sessions' => '150',
            'screenPageViews' => '900', 'engagementRate' => '0.6543',
        ]],
        'date|' => [
            ['date' => '20260901', 'activeUsers' => '30', 'sessions' => '35'],
            ['date' => '20260902', 'activeUsers' => '45', 'sessions' => '50'],
        ],
        'customEvent:phone_id,customEvent:phone_name,customEvent:brand|view_phone' => [
            ['customEvent:phone_id' => '1', 'customEvent:phone_name' => 'iPhone 16 Pro', 'customEvent:brand' => 'Apple', 'eventCount' => '50'],
            ['customEvent:phone_id' => '2', 'customEvent:phone_name' => 'Galaxy S25 Ultra', 'customEvent:brand' => 'Samsung', 'eventCount' => '30'],
        ],
        'customEvent:budget_range|budget_selected' => [
            ['customEvent:budget_range' => '20000-30000', 'eventCount' => '42'],
            ['customEvent:budget_range' => '30000-40000', 'eventCount' => '24'],
        ],
        'customEvent:brand|brand_viewed' => [
            ['customEvent:brand' => 'Samsung', 'eventCount' => '80'],
            ['customEvent:brand' => 'Apple', 'eventCount' => '60'],
        ],
        'customEvent:search_term|search' => [
            ['customEvent:search_term' => 'galaxy a55', 'eventCount' => '15'],
        ],
        'customEvent:phone_ids,customEvent:phone_names,customEvent:brands|compare_phones' => [
            ['customEvent:phone_ids' => '1,2', 'customEvent:phone_names' => 'iPhone 16 Pro,Galaxy S25 Ultra', 'customEvent:brands' => 'Apple,Samsung', 'eventCount' => '5'],
            ['customEvent:phone_ids' => '1,3', 'customEvent:phone_names' => 'iPhone 16 Pro,Pixel 9', 'customEvent:brands' => 'Apple,Google', 'eventCount' => '3'],
        ],
        'eventName|find_phone_started,find_phone_completed,recommendation_viewed,recommendation_phone_clicked' => [
            ['eventName' => 'find_phone_started', 'eventCount' => '100'],
            ['eventName' => 'find_phone_completed', 'eventCount' => '40'],
            ['eventName' => 'recommendation_viewed', 'eventCount' => '40'],
            ['eventName' => 'recommendation_phone_clicked', 'eventCount' => '25'],
        ],
        'customEvent:budget_range|find_phone_completed' => [
            ['customEvent:budget_range' => '20000-30000', 'eventCount' => '20'],
            ['customEvent:budget_range' => '30000-40000', 'eventCount' => '20'],
        ],
        'customEvent:usage_category|find_phone_completed' => [
            ['customEvent:usage_category' => 'gaming', 'eventCount' => '25'],
            ['customEvent:usage_category' => 'camera', 'eventCount' => '15'],
        ],
        'customEvent:preferred_brands|find_phone_completed' => [
            ['customEvent:preferred_brands' => 'Samsung,Xiaomi', 'eventCount' => '10'],
            ['customEvent:preferred_brands' => 'Apple', 'eventCount' => '8'],
        ],
    ];
}
