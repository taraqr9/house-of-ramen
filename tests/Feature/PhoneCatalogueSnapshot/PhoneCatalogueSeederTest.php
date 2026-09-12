<?php

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneDataReview;
use App\Models\PhoneImage;
use App\Models\PhoneMarketPrice;
use App\Models\PhonePrice;
use App\Models\PhonePriceHistory;
use App\Models\PhoneStore;
use App\Models\PhoneVariant;
use App\Models\User;
use Database\Seeders\PhoneCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->directory = storage_path('framework/testing/catalogue-seed-'.uniqid());
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
            'network_bands' => [['network_type' => '5G', 'bands' => 'n1/n3/n41']],
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
            'reviews' => [[
                'reason' => 'low_confidence', 'status' => 'approved', 'resolution_note' => 'Checked in test fixture.',
            ]],
        ],
    ]]));
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

it('loads brands, phones, specs, variants, prices, images, and resolved reviews from the snapshot', function () {
    $this->seed(PhoneCatalogueSeeder::class);

    $phone = Phone::where('slug', 'seed-phone-a')->first();

    expect($phone)->not->toBeNull();
    expect($phone->is_active)->toBeTrue();
    expect($phone->spec->processor)->toBe('Test Chip');
    expect(PhoneVariant::where('phone_id', $phone->id)->count())->toBe(1);
    expect(PhonePrice::whereHas('variant', fn ($q) => $q->where('phone_id', $phone->id))->count())->toBe(1);
    expect(PhoneMarketPrice::whereHas('variant', fn ($q) => $q->where('phone_id', $phone->id))->count())->toBe(1);
    expect(PhoneDataReview::where('phone_id', $phone->id)->where('status', 'pending')->count())->toBe(0);
    expect(PhoneDataReview::where('phone_id', $phone->id)->where('status', 'approved')->count())->toBe(1);

    $image = PhoneImage::where('phone_id', $phone->id)->first();
    expect($image)->not->toBeNull();
    expect($image->status->value)->toBe('verified');
    expect(Storage::disk('public')->exists($image->path))->toBeTrue();
});

it('is idempotent: seeding twice creates no duplicate rows', function () {
    $this->seed(PhoneCatalogueSeeder::class);
    $this->seed(PhoneCatalogueSeeder::class);

    expect(Phone::where('slug', 'seed-phone-a')->count())->toBe(1);
    expect(PhoneVariant::where('slug', 'seed-phone-a-8-128')->count())->toBe(1);

    $phone = Phone::where('slug', 'seed-phone-a')->first();
    expect(PhoneImage::where('phone_id', $phone->id)->count())->toBe(1);
    expect(PhonePrice::whereHas('variant', fn ($q) => $q->where('phone_id', $phone->id))->count())->toBe(1);
    expect(PhoneMarketPrice::whereHas('variant', fn ($q) => $q->where('phone_id', $phone->id))->count())->toBe(1);
    expect(PhoneDataReview::where('phone_id', $phone->id)->count())->toBe(1);
});

it('does nothing (and does not fail) when no snapshot directory exists yet', function () {
    File::deleteDirectory($this->directory);

    $this->seed(PhoneCatalogueSeeder::class);

    expect(Phone::count())->toBe(0);
});

// -----------------------------------------------------------------------
// phone_market_prices is derived data (see PhoneCatalogueSeeder's
// docblock) - it must be recomputed from seeded phone_prices via the
// real PriceAggregator, never trusted from the snapshot (which no
// longer even contains it - see ExportPhoneCatalogueCommand).
// -----------------------------------------------------------------------

it('recomputes the market price from seeded raw prices via the real PriceAggregator, including outlier rejection', function () {
    // Three retailer observations for the same (variant, unofficial_bd):
    // two agree closely, one is a genuine statistical outlier - if this
    // seeder called PriceAggregator for real (rather than trusting a
    // hand-written aggregate), the outlier must be excluded exactly the
    // way the admin panel/import pipeline already behaves.
    File::put("{$this->directory}/seedbrand.json", json_encode(['brand_slug' => 'seedbrand', 'phones' => [
        [
            'slug' => 'seed-phone-a', 'name' => 'Seed Phone A', 'status' => 'available', 'is_active' => true,
            'variants' => [[
                'slug' => 'seed-phone-a-8-128', 'ram_gb' => 8, 'storage_gb' => 128, 'is_active' => true,
                'prices' => [
                    ['store_slug' => 'seed-store', 'price_type' => 'unofficial_bd', 'amount' => '20000.00', 'currency' => 'BDT'],
                    ['store_slug' => 'seed-store-2', 'price_type' => 'unofficial_bd', 'amount' => '20500.00', 'currency' => 'BDT'],
                    ['store_slug' => 'seed-store-3', 'price_type' => 'unofficial_bd', 'amount' => '95000.00', 'currency' => 'BDT'],
                ],
            ]],
        ],
    ]]));

    File::put("{$this->directory}/stores.json", json_encode(['stores' => [
        ['slug' => 'seed-store', 'name' => 'Seed Store', 'type' => 'online', 'is_active' => true],
        ['slug' => 'seed-store-2', 'name' => 'Seed Store 2', 'type' => 'online', 'is_active' => true],
        ['slug' => 'seed-store-3', 'name' => 'Seed Store 3', 'type' => 'online', 'is_active' => true],
    ]]));

    $this->seed(PhoneCatalogueSeeder::class);

    $variant = PhoneVariant::where('slug', 'seed-phone-a-8-128')->firstOrFail();
    $marketPrice = PhoneMarketPrice::where('phone_variant_id', $variant->id)->where('price_type', 'unofficial_bd')->firstOrFail();

    // The 95,000 outlier must be excluded - the aggregate reflects only
    // the two agreeing observations (median 20,250, exactly what
    // PriceAggregator::robustAggregate() computes independently, proving
    // the seeder called the real class rather than re-implementing it).
    expect((float) $marketPrice->price)->toBe(20250.0)
        ->and($marketPrice->retailer_count)->toBe(2)
        ->and($marketPrice->outlier_count)->toBe(1);
});

it('keeps official and unofficial market prices as separate rows, never merged into one', function () {
    File::put("{$this->directory}/seedbrand.json", json_encode(['brand_slug' => 'seedbrand', 'phones' => [
        [
            'slug' => 'seed-phone-a', 'name' => 'Seed Phone A', 'status' => 'available', 'is_active' => true,
            'variants' => [[
                'slug' => 'seed-phone-a-8-128', 'ram_gb' => 8, 'storage_gb' => 128, 'is_active' => true,
                'prices' => [
                    ['store_slug' => 'seed-store', 'price_type' => 'official_bd', 'amount' => '35000.00', 'currency' => 'BDT'],
                    ['store_slug' => 'seed-store', 'price_type' => 'unofficial_bd', 'amount' => '27000.00', 'currency' => 'BDT'],
                ],
            ]],
        ],
    ]]));

    $this->seed(PhoneCatalogueSeeder::class);

    $variant = PhoneVariant::where('slug', 'seed-phone-a-8-128')->firstOrFail();
    $marketPrices = PhoneMarketPrice::where('phone_variant_id', $variant->id)->get()->keyBy(fn ($mp) => $mp->price_type->value);

    expect($marketPrices)->toHaveCount(2)
        ->and((float) $marketPrices['official_bd']->price)->toBe(35000.0)
        ->and((float) $marketPrices['unofficial_bd']->price)->toBe(27000.0);
});

it('clears a stale market price when the new snapshot no longer has any observation for that price type', function () {
    // Simulates a real deploy scenario: a variant previously had an
    // official price on record; the latest verified snapshot no longer
    // includes one (e.g. the only official retailer delisted it).
    // PriceAggregator::recalculate() must be re-run for every price type,
    // not just the ones present in the new data, or this row would
    // silently linger forever.
    $this->seed(PhoneCatalogueSeeder::class);
    $variant = PhoneVariant::where('slug', 'seed-phone-a-8-128')->firstOrFail();
    PhoneMarketPrice::create([
        'phone_variant_id' => $variant->id, 'price_type' => 'official_bd', 'price' => 99999,
        'price_min' => 99999, 'price_max' => 99999, 'observation_count' => 1, 'retailer_count' => 1, 'calculated_at' => now(),
    ]);

    $this->seed(PhoneCatalogueSeeder::class);

    expect(PhoneMarketPrice::where('phone_variant_id', $variant->id)->where('price_type', 'official_bd')->exists())->toBeFalse();
});

it('produces the same market price after seeding the same snapshot twice', function () {
    File::put("{$this->directory}/seedbrand.json", json_encode(['brand_slug' => 'seedbrand', 'phones' => [
        [
            'slug' => 'seed-phone-a', 'name' => 'Seed Phone A', 'status' => 'available', 'is_active' => true,
            'variants' => [[
                'slug' => 'seed-phone-a-8-128', 'ram_gb' => 8, 'storage_gb' => 128, 'is_active' => true,
                'prices' => [['store_slug' => 'seed-store', 'price_type' => 'unofficial_bd', 'amount' => '29999.00', 'currency' => 'BDT']],
            ]],
        ],
    ]]));

    $this->seed(PhoneCatalogueSeeder::class);
    $variant = PhoneVariant::where('slug', 'seed-phone-a-8-128')->firstOrFail();
    $firstPrice = (float) PhoneMarketPrice::where('phone_variant_id', $variant->id)->firstOrFail()->price;

    $this->seed(PhoneCatalogueSeeder::class);
    $secondPrice = (float) PhoneMarketPrice::where('phone_variant_id', $variant->id)->firstOrFail()->price;

    expect(PhoneMarketPrice::where('phone_variant_id', $variant->id)->count())->toBe(1)
        ->and($secondPrice)->toBe($firstPrice)
        ->and($secondPrice)->toBe(29999.0);
});

it('never writes phone_price_history from a bulk reseed - it cannot know whether a value genuinely just changed', function () {
    $this->seed(PhoneCatalogueSeeder::class);
    $this->seed(PhoneCatalogueSeeder::class);

    expect(PhonePriceHistory::count())->toBe(0);
});

it('keeps a Global and a Chinese variant of the same phone as independent variants with independent market prices', function () {
    File::put("{$this->directory}/seedbrand.json", json_encode(['brand_slug' => 'seedbrand', 'phones' => [
        [
            'slug' => 'seed-phone-a', 'name' => 'Seed Phone A', 'status' => 'available', 'is_active' => true,
            'variants' => [
                [
                    'slug' => 'seed-phone-a-global', 'ram_gb' => 12, 'storage_gb' => 256, 'region' => 'Global', 'is_active' => true,
                    'prices' => [['store_slug' => 'seed-store', 'price_type' => 'unofficial_bd', 'amount' => '80000.00', 'currency' => 'BDT']],
                ],
                [
                    'slug' => 'seed-phone-a-china', 'ram_gb' => 12, 'storage_gb' => 256, 'region' => 'China', 'is_active' => true,
                    'prices' => [['store_slug' => 'seed-store', 'price_type' => 'unofficial_bd', 'amount' => '65000.00', 'currency' => 'BDT']],
                ],
            ],
        ],
    ]]));

    $this->seed(PhoneCatalogueSeeder::class);

    $global = PhoneVariant::where('slug', 'seed-phone-a-global')->firstOrFail();
    $china = PhoneVariant::where('slug', 'seed-phone-a-china')->firstOrFail();

    expect($global->id)->not->toBe($china->id)
        ->and($global->region)->toBe('Global')
        ->and($china->region)->toBe('China')
        ->and((float) PhoneMarketPrice::where('phone_variant_id', $global->id)->firstOrFail()->price)->toBe(80000.0)
        ->and((float) PhoneMarketPrice::where('phone_variant_id', $china->id)->firstOrFail()->price)->toBe(65000.0);
});

// -----------------------------------------------------------------------
// Production-only / unrelated data must never be touched by a catalogue
// reseed - only rows the snapshot actually describes are written.
// -----------------------------------------------------------------------

it('never touches users, activity log entries, or a brand/phone absent from the snapshot', function () {
    $admin = User::factory()->create(['username' => 'localadmin']);
    $activityCountBefore = Activity::count();

    $unrelatedBrand = Brand::factory()->create(['slug' => 'not-in-any-snapshot']);
    $unrelatedPhone = Phone::factory()->for($unrelatedBrand)->create(['slug' => 'untouched-phone']);
    $unrelatedVariant = PhoneVariant::factory()->for($unrelatedPhone)->create();
    $unrelatedPrice = PhonePrice::factory()->for($unrelatedVariant, 'variant')->create(['amount' => 12345]);

    $this->seed(PhoneCatalogueSeeder::class);

    expect(User::where('id', $admin->id)->exists())->toBeTrue()
        ->and(Activity::count())->toBeGreaterThanOrEqual($activityCountBefore)
        ->and(Brand::where('slug', 'not-in-any-snapshot')->exists())->toBeTrue()
        ->and(Phone::where('slug', 'untouched-phone')->exists())->toBeTrue()
        ->and(PhoneVariant::where('id', $unrelatedVariant->id)->exists())->toBeTrue()
        ->and($unrelatedPrice->fresh()->amount)->toEqual('12345.00');
});

it('never deletes an existing raw phone_prices row just because a reseed happened - only phone_market_prices is disposable', function () {
    $this->seed(PhoneCatalogueSeeder::class);
    $variant = PhoneVariant::where('slug', 'seed-phone-a-8-128')->firstOrFail();

    // A price from a store the committed snapshot doesn't mention at all
    // (e.g. an admin-entered manual correction made after the last export).
    $otherStore = PhoneStore::factory()->create(['slug' => 'admin-only-store']);
    $manualPrice = PhonePrice::factory()->for($variant, 'variant')->create([
        'store_id' => $otherStore->id, 'price_type' => 'unofficial_bd', 'amount' => 26000,
    ]);

    $this->seed(PhoneCatalogueSeeder::class);

    expect(PhonePrice::where('id', $manualPrice->id)->exists())->toBeTrue();
});

it('preserves two reviews that share the same reason and status but different created_at, across repeated reseeds', function () {
    // A phone can legitimately have more than one review with the same
    // reason+status (e.g. two separate nightly collect-images runs each
    // raising their own image_needs_review flag) - regression test for
    // the bug where the seeder's upsert key (reason+status only) silently
    // collapsed these into a single row on every fresh reseed.
    File::put("{$this->directory}/seedbrand.json", json_encode(['brand_slug' => 'seedbrand', 'phones' => [
        [
            'slug' => 'seed-phone-a',
            'name' => 'Seed Phone A',
            'status' => 'available',
            'is_active' => true,
            'identity_confidence' => 90,
            'spec' => ['processor' => 'Test Chip', 'battery_capacity_mah' => 5000],
            'reviews' => [
                [
                    'reason' => 'image_needs_review', 'status' => 'resolved',
                    'resolution_note' => 'First run.', 'created_at' => '2026-08-28T13:45:15+00:00',
                ],
                [
                    'reason' => 'image_needs_review', 'status' => 'resolved',
                    'resolution_note' => 'Second run, two days later.', 'created_at' => '2026-08-30T07:07:16+00:00',
                ],
            ],
        ],
    ]]));

    $this->seed(PhoneCatalogueSeeder::class);
    $this->seed(PhoneCatalogueSeeder::class);

    $phone = Phone::where('slug', 'seed-phone-a')->firstOrFail();
    $reviews = PhoneDataReview::where('phone_id', $phone->id)->orderBy('created_at')->get();

    expect($reviews)->toHaveCount(2);
    expect($reviews[0]->resolution_note)->toBe('First run.');
    expect($reviews[1]->resolution_note)->toBe('Second run, two days later.');
    expect($reviews[0]->created_at->eq('2026-08-28T13:45:15+00:00'))->toBeTrue();
    expect($reviews[1]->created_at->eq('2026-08-30T07:07:16+00:00'))->toBeTrue();
});
