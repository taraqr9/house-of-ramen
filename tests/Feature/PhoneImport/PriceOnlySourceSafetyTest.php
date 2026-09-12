<?php

use App\Enums\ImportRunTypeEnum;
use App\Enums\PhoneStatusEnum;
use App\Enums\SourceTypeEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneSource;
use App\Models\PhoneSpec;
use App\Services\PhoneImport\Contracts\PhoneSourceProvider;
use App\Services\PhoneImport\PhoneImportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A minimal PhoneSourceProvider standing in for a real retailer price
 * adapter - reports brand/model/variant/price only, exactly like
 * App\Services\PhoneImport\Sources\Retailers\RetailerListingSource
 * subclasses do, with no spec/software fields at all.
 */
function priceOnlySource(array $item): PhoneSourceProvider
{
    return new class($item) implements PhoneSourceProvider
    {
        public function __construct(private array $item) {}

        public function key(): string
        {
            return 'price_only_test_source';
        }

        public function type(): SourceTypeEnum
        {
            return SourceTypeEnum::BD_RETAILER;
        }

        public function fetchBatch(?array $cursor, int $limit): array
        {
            return ['items' => $cursor === null ? [$this->item] : [], 'cursor' => ['done' => true], 'done' => true];
        }
    };
}

it('does not degrade an existing phone spec/software confidence when a later price-only source run supplies no spec data', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    $phone = Phone::factory()->for($brand, 'brand')->create([
        'name' => 'Galaxy S24 Ultra',
        'slug' => 'samsung-galaxy-s24-ultra',
        'spec_confidence' => 92,
        'software_confidence' => 88,
    ]);
    PhoneSpec::create(['phone_id' => $phone->id, 'processor' => 'Snapdragon 8 Gen 3', 'os' => 'Android 14']);

    $source = PhoneSource::create([
        'key' => 'price_only_test_source', 'name' => 'Test Retailer', 'type' => 'bd_retailer',
        'reliability_score' => 80, 'requires_review' => false, 'is_active' => true,
    ]);

    $provider = priceOnlySource([
        'brand' => 'Samsung',
        'model' => 'Galaxy S24 Ultra',
        'variants' => [[
            'ram' => '12 GB', 'storage' => '256 GB', 'is_official_bd' => true,
            'price' => ['official_bd' => ['amount' => 250000, 'store' => 'Test Retailer']],
        ]],
    ]);

    app(PhoneImportRunner::class)->run($provider, $source, ImportRunTypeEnum::MANUAL);

    $phone->refresh();

    expect($phone->spec_confidence)->toBe(92)
        ->and($phone->software_confidence)->toBe(88)
        ->and(PhoneSpec::where('phone_id', $phone->id)->first()->processor)->toBe('Snapdragon 8 Gen 3');
});

it('does not blank an existing phone model_number/announced_date/category when a price-only source omits them', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    $phone = Phone::factory()->for($brand, 'brand')->create([
        'name' => 'Galaxy S24 Ultra',
        'slug' => 'samsung-galaxy-s24-ultra',
        'model_number' => 'SM-S928B',
        'announced_date' => '2024-01-17',
        'category' => 'flagship',
        'status' => PhoneStatusEnum::AVAILABLE,
    ]);

    $source = PhoneSource::create([
        'key' => 'price_only_test_source', 'name' => 'Test Retailer', 'type' => 'bd_retailer',
        'reliability_score' => 80, 'requires_review' => false, 'is_active' => true,
    ]);

    $provider = priceOnlySource([
        'brand' => 'Samsung',
        'model' => 'Galaxy S24 Ultra',
        'variants' => [[
            'ram' => '12 GB', 'storage' => '256 GB', 'is_official_bd' => true,
            'price' => ['official_bd' => ['amount' => 250000, 'store' => 'Test Retailer']],
        ]],
    ]);

    app(PhoneImportRunner::class)->run($provider, $source, ImportRunTypeEnum::MANUAL);

    $phone->refresh();

    expect($phone->model_number)->toBe('SM-S928B')
        ->and($phone->announced_date->toDateString())->toBe('2024-01-17')
        ->and($phone->category)->toBe('flagship');
});

it('does not blank an existing variant color when a price-only source does not name one', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    $phone = Phone::factory()->for($brand, 'brand')->create(['name' => 'Galaxy S24 Ultra', 'slug' => 'samsung-galaxy-s24-ultra']);
    $variant = $phone->variants()->create([
        'slug' => 'samsung-galaxy-s24-ultra-12-256-global', 'ram_gb' => 12, 'storage_gb' => 256,
        'region' => 'Global', 'color' => 'Titanium Black', 'is_official_bd' => true, 'status' => 'available', 'is_active' => true,
    ]);

    $source = PhoneSource::create([
        'key' => 'price_only_test_source', 'name' => 'Test Retailer', 'type' => 'bd_retailer',
        'reliability_score' => 80, 'requires_review' => false, 'is_active' => true,
    ]);

    $provider = priceOnlySource([
        'brand' => 'Samsung',
        'model' => 'Galaxy S24 Ultra',
        'variants' => [[
            'ram' => '12 GB', 'storage' => '256 GB', 'region' => 'Global', 'is_official_bd' => true,
            'price' => ['official_bd' => ['amount' => 250000, 'store' => 'Test Retailer']],
        ]],
    ]);

    app(PhoneImportRunner::class)->run($provider, $source, ImportRunTypeEnum::MANUAL);

    expect($variant->fresh()->color)->toBe('Titanium Black');
});

it('matches an existing variant by ram/storage/region alone - color is descriptive, not part of variant identity', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    $phone = Phone::factory()->for($brand, 'brand')->create(['name' => 'Galaxy S24 Ultra', 'slug' => 'samsung-galaxy-s24-ultra']);
    $variant = $phone->variants()->create([
        'slug' => 'samsung-galaxy-s24-ultra-12-256-global', 'ram_gb' => 12, 'storage_gb' => 256,
        'region' => 'Global', 'color' => 'Titanium Black', 'is_official_bd' => true, 'status' => 'available', 'is_active' => true,
    ]);

    $source = PhoneSource::create([
        'key' => 'price_only_test_source', 'name' => 'Test Retailer', 'type' => 'bd_retailer',
        'reliability_score' => 80, 'requires_review' => false, 'is_active' => true,
    ]);

    // A different retailer reports a different color for the "same"
    // RAM/storage/region - must update the SAME variant row, not create
    // a second one.
    $provider = priceOnlySource([
        'brand' => 'Samsung',
        'model' => 'Galaxy S24 Ultra',
        'variants' => [[
            'ram' => '12 GB', 'storage' => '256 GB', 'region' => 'Global', 'color' => 'Titanium Violet', 'is_official_bd' => true,
            'price' => ['official_bd' => ['amount' => 250000, 'store' => 'Test Retailer']],
        ]],
    ]);

    app(PhoneImportRunner::class)->run($provider, $source, ImportRunTypeEnum::MANUAL);

    expect($phone->variants()->count())->toBe(1)
        ->and($variant->fresh()->color)->toBe('Titanium Violet'); // a source that DOES name a color still updates it
});
