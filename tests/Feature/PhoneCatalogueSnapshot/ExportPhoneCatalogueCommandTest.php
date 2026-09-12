<?php

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneAvailability;
use App\Models\PhoneDataReview;
use App\Models\PhoneImage;
use App\Models\PhoneMarketPrice;
use App\Models\PhoneNetworkBand;
use App\Models\PhonePrice;
use App\Models\PhoneSpec;
use App\Models\PhoneStore;
use App\Models\PhoneVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->directory = storage_path('framework/testing/catalogue-export-'.uniqid());
    config(['phone_catalogue_snapshot.directory' => $this->directory]);
    Storage::fake('public');
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

it('exports only phones with zero pending reviews, skipping the rest', function () {
    $brand = Brand::factory()->create(['slug' => 'testbrand', 'name' => 'TestBrand']);

    $clean = Phone::factory()->for($brand)->create(['slug' => 'clean-phone', 'is_active' => true]);
    PhoneSpec::factory()->for($clean)->create();
    PhoneVariant::factory()->for($clean)->create();

    $pending = Phone::factory()->for($brand)->create(['slug' => 'pending-phone', 'is_active' => false]);
    PhoneDataReview::create(['phone_id' => $pending->id, 'reason' => 'low_confidence', 'status' => 'pending']);

    $this->artisan('phones:export-catalogue')->assertExitCode(0);

    $payload = json_decode(File::get("{$this->directory}/testbrand.json"), true);
    $slugs = collect($payload['phones'])->pluck('slug')->all();

    expect($slugs)->toContain('clean-phone')
        ->and($slugs)->not->toContain('pending-phone');
});

it('includes the actual verified image file, not just a database reference', function () {
    $brand = Brand::factory()->create(['slug' => 'imagebrand']);
    $phone = Phone::factory()->for($brand)->create(['slug' => 'imaged-phone', 'is_active' => true]);

    Storage::disk('public')->put("phones/{$phone->id}/test.webp", 'fake-image-bytes');

    PhoneImage::factory()->for($phone)->create([
        'is_primary' => true,
        'path' => "phones/{$phone->id}/test.webp",
        'status' => 'verified',
    ]);

    $this->artisan('phones:export-catalogue')->assertExitCode(0);

    $payload = json_decode(File::get("{$this->directory}/imagebrand.json"), true);
    $image = $payload['phones'][0]['images'][0];

    expect($image['seed_file'])->not->toBeNull();
    expect(File::exists("{$this->directory}/images/{$image['seed_file']}"))->toBeTrue();
});

it('includes created_at on every exported review, not just reason/status/reviewed_at', function () {
    // Regression test: the seeder's upsert key needs reason+status+
    // created_at to tell apart two reviews sharing the same reason and
    // status, so the exporter must actually ship created_at - it used to
    // omit it entirely.
    $brand = Brand::factory()->create(['slug' => 'reviewbrand']);
    $phone = Phone::factory()->for($brand)->create(['slug' => 'reviewed-phone', 'is_active' => true]);

    $review = PhoneDataReview::create([
        'phone_id' => $phone->id,
        'reason' => 'low_confidence',
        'status' => 'approved',
    ]);

    $this->artisan('phones:export-catalogue')->assertExitCode(0);

    $payload = json_decode(File::get("{$this->directory}/reviewbrand.json"), true);
    $exportedReview = $payload['phones'][0]['reviews'][0];

    expect($exportedReview['created_at'])->not->toBeNull();
    expect(Carbon::parse($exportedReview['created_at'])->eq($review->created_at))->toBeTrue();
});

it('can be scoped to a single brand without touching others', function () {
    $brandA = Brand::factory()->create(['slug' => 'brand-a']);
    $brandB = Brand::factory()->create(['slug' => 'brand-b']);
    Phone::factory()->for($brandA)->create(['slug' => 'phone-a', 'is_active' => true]);
    Phone::factory()->for($brandB)->create(['slug' => 'phone-b', 'is_active' => true]);

    $this->artisan('phones:export-catalogue', ['--brand' => 'brand-a'])->assertExitCode(0);

    expect(File::exists("{$this->directory}/brand-a.json"))->toBeTrue();
    expect(File::exists("{$this->directory}/brand-b.json"))->toBeFalse();
});

// -----------------------------------------------------------------------
// Determinism - running the exporter twice against unchanged data must
// produce byte-identical files, so `git diff` against the committed
// snapshot is a trustworthy drift signal (see task: "harden the existing
// system", section 1/5).
// -----------------------------------------------------------------------

it('produces byte-identical output on a second export with no database changes in between', function () {
    $brand = Brand::factory()->create(['slug' => 'stablebrand']);
    $phone = Phone::factory()->for($brand)->create(['slug' => 'stable-phone', 'is_active' => true]);
    PhoneSpec::factory()->for($phone)->create();
    PhoneNetworkBand::create(['phone_id' => $phone->id, 'network_type' => '5G', 'bands' => 'n1/n3']);
    PhoneNetworkBand::create(['phone_id' => $phone->id, 'network_type' => '4G', 'bands' => 'B1/B3']);

    $variant = PhoneVariant::factory()->for($phone)->create(['ram_gb' => 8, 'storage_gb' => 256]);
    $storeOne = PhoneStore::factory()->create(['slug' => 'store-one']);
    $storeTwo = PhoneStore::factory()->create(['slug' => 'store-two']);
    PhonePrice::factory()->for($variant, 'variant')->create(['store_id' => $storeOne->id, 'price_type' => 'official_bd', 'amount' => 50000]);
    PhonePrice::factory()->for($variant, 'variant')->create(['store_id' => $storeTwo->id, 'price_type' => 'unofficial_bd', 'amount' => 42000]);
    PhoneAvailability::create(['phone_variant_id' => $variant->id, 'store_id' => $storeOne->id, 'status' => 'in_stock']);

    Storage::disk('public')->put("phones/{$phone->id}/a.webp", 'aaa');
    Storage::disk('public')->put("phones/{$phone->id}/b.webp", 'bbb');
    PhoneImage::factory()->for($phone)->create(['path' => "phones/{$phone->id}/a.webp", 'is_primary' => true, 'sort_order' => 0, 'status' => 'verified']);
    PhoneImage::factory()->for($phone)->create(['path' => "phones/{$phone->id}/b.webp", 'is_primary' => false, 'sort_order' => 0, 'status' => 'verified']);

    PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'low_confidence', 'status' => 'approved']);

    $this->artisan('phones:export-catalogue', ['--brand' => 'stablebrand'])->assertExitCode(0);
    $firstRun = File::get("{$this->directory}/stablebrand.json");

    File::deleteDirectory("{$this->directory}/images");

    $this->artisan('phones:export-catalogue', ['--brand' => 'stablebrand'])->assertExitCode(0);
    $secondRun = File::get("{$this->directory}/stablebrand.json");

    expect($secondRun)->toBe($firstRun);
});

it('orders every nested collection deterministically regardless of database insertion order', function () {
    $brand = Brand::factory()->create(['slug' => 'orderbrand']);
    $phone = Phone::factory()->for($brand)->create(['slug' => 'order-phone', 'is_active' => true]);

    // Insert everything in a deliberately reversed/scrambled order so a
    // passing test can only mean the exporter imposes its own order, not
    // that it happened to match insertion order by coincidence.
    PhoneNetworkBand::create(['phone_id' => $phone->id, 'network_type' => '5G']);
    PhoneNetworkBand::create(['phone_id' => $phone->id, 'network_type' => '4G']);

    $variantB = PhoneVariant::factory()->for($phone)->create(['ram_gb' => 12, 'storage_gb' => 512, 'region' => 'Global']);
    $variantA = PhoneVariant::factory()->for($phone)->create(['ram_gb' => 8, 'storage_gb' => 128, 'region' => 'Global']);

    $storeZ = PhoneStore::factory()->create(['slug' => 'zzz-store']);
    $storeA = PhoneStore::factory()->create(['slug' => 'aaa-store']);
    PhonePrice::factory()->for($variantA, 'variant')->create(['store_id' => $storeZ->id, 'price_type' => 'unofficial_bd', 'amount' => 10000]);
    PhonePrice::factory()->for($variantA, 'variant')->create(['store_id' => $storeA->id, 'price_type' => 'unofficial_bd', 'amount' => 11000]);
    PhoneAvailability::create(['phone_variant_id' => $variantA->id, 'store_id' => $storeZ->id, 'status' => 'in_stock']);
    PhoneAvailability::create(['phone_variant_id' => $variantA->id, 'store_id' => $storeA->id, 'status' => 'in_stock']);

    PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'low_confidence', 'status' => 'approved', 'created_at' => now()->subDay()]);
    PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'image_needs_review', 'status' => 'resolved', 'created_at' => now()]);

    $this->artisan('phones:export-catalogue', ['--brand' => 'orderbrand'])->assertExitCode(0);
    $payload = json_decode(File::get("{$this->directory}/orderbrand.json"), true);
    $exportedPhone = $payload['phones'][0];

    expect(collect($exportedPhone['network_bands'])->pluck('network_type')->all())->toBe(['4G', '5G']);
    expect(collect($exportedPhone['variants'])->pluck('slug')->all())->toBe([$variantA->slug, $variantB->slug]);

    $pricesForVariantA = collect($exportedPhone['variants'])->firstWhere('slug', $variantA->slug)['prices'];
    expect(collect($pricesForVariantA)->pluck('store_slug')->all())->toBe(['aaa-store', 'zzz-store']);

    $availabilitiesForVariantA = collect($exportedPhone['variants'])->firstWhere('slug', $variantA->slug)['availabilities'];
    expect(collect($availabilitiesForVariantA)->pluck('store_slug')->all())->toBe(['aaa-store', 'zzz-store']);

    expect(collect($exportedPhone['reviews'])->pluck('reason')->all())->toBe(['image_needs_review', 'low_confidence']);
});

it('never exports phone_market_prices - it is derived data recomputed at seed time, not snapshotted', function () {
    $brand = Brand::factory()->create(['slug' => 'noaggregatebrand']);
    $phone = Phone::factory()->for($brand)->create(['slug' => 'no-aggregate-phone', 'is_active' => true]);
    $variant = PhoneVariant::factory()->for($phone)->create();
    $store = PhoneStore::factory()->create();
    PhonePrice::factory()->for($variant, 'variant')->create(['store_id' => $store->id, 'price_type' => 'unofficial_bd', 'amount' => 15000]);
    PhoneMarketPrice::factory()->for($variant, 'variant')->create(['price_type' => 'unofficial_bd', 'price' => 15000]);

    $this->artisan('phones:export-catalogue', ['--brand' => 'noaggregatebrand'])->assertExitCode(0);

    $payload = json_decode(File::get("{$this->directory}/noaggregatebrand.json"), true);
    $exportedVariant = $payload['phones'][0]['variants'][0];

    expect($exportedVariant)->not->toHaveKey('market_prices');
});

it('never embeds a raw database id in an exported image filename', function () {
    $brand = Brand::factory()->create(['slug' => 'idsafebrand']);
    $phone = Phone::factory()->for($brand)->create(['slug' => 'id-safe-phone', 'is_active' => true]);

    Storage::disk('public')->put("phones/{$phone->id}/x.webp", 'xxx');
    // Force a high, distinctive auto-increment id so an accidental id-based
    // filename would be obviously visible/wrong in the assertion below.
    $image = PhoneImage::factory()->for($phone)->create(['path' => "phones/{$phone->id}/x.webp", 'is_primary' => true, 'sort_order' => 0, 'status' => 'verified']);
    PhoneImage::query()->where('id', $image->id)->update(['id' => 999999]);

    $this->artisan('phones:export-catalogue', ['--brand' => 'idsafebrand'])->assertExitCode(0);

    $payload = json_decode(File::get("{$this->directory}/idsafebrand.json"), true);
    $seedFile = $payload['phones'][0]['images'][0]['seed_file'];

    expect($seedFile)->toBe('id-safe-phone/0-0.webp')
        ->and($seedFile)->not->toContain('999999');
});

it('produces a real, visible diff when the underlying data actually changes - proving drift is genuinely detectable', function () {
    // The other direction of the determinism guarantee: "no DB change ->
    // no diff" is only a trustworthy drift-detection signal (per the
    // `phones:export-catalogue && git diff --exit-code seed-data/` workflow)
    // if a genuine change reliably DOES produce a diff.
    $brand = Brand::factory()->create(['slug' => 'driftbrand']);
    $phone = Phone::factory()->for($brand)->create(['slug' => 'drift-phone', 'is_active' => true]);
    $variant = PhoneVariant::factory()->for($phone)->create();
    $store = PhoneStore::factory()->create(['slug' => 'drift-store']);
    PhonePrice::factory()->for($variant, 'variant')->create(['store_id' => $store->id, 'price_type' => 'unofficial_bd', 'amount' => 30000]);

    $this->artisan('phones:export-catalogue', ['--brand' => 'driftbrand'])->assertExitCode(0);
    $before = File::get("{$this->directory}/driftbrand.json");

    PhonePrice::where('phone_variant_id', $variant->id)->update(['amount' => 31500]);

    $this->artisan('phones:export-catalogue', ['--brand' => 'driftbrand'])->assertExitCode(0);
    $after = File::get("{$this->directory}/driftbrand.json");

    expect($after)->not->toBe($before)
        ->and($after)->toContain('31500.00')
        ->and($before)->not->toContain('31500.00');
});

it('never leaks a raw database id anywhere in the exported payload - only slugs and keys identify records', function () {
    $brand = Brand::factory()->create(['slug' => 'noidleakbrand']);
    $phone = Phone::factory()->for($brand)->create(['slug' => 'no-id-leak-phone', 'is_active' => true]);
    $variant = PhoneVariant::factory()->for($phone)->create();
    $store = PhoneStore::factory()->create(['slug' => 'no-id-leak-store']);
    PhonePrice::factory()->for($variant, 'variant')->create(['store_id' => $store->id, 'price_type' => 'unofficial_bd', 'amount' => 18000]);
    PhoneAvailability::create(['phone_variant_id' => $variant->id, 'store_id' => $store->id, 'status' => 'in_stock']);
    Storage::disk('public')->put("phones/{$phone->id}/x.webp", 'xxx');
    PhoneImage::factory()->for($phone)->create(['path' => "phones/{$phone->id}/x.webp", 'is_primary' => true, 'sort_order' => 0, 'status' => 'verified']);
    PhoneDataReview::create(['phone_id' => $phone->id, 'reason' => 'low_confidence', 'status' => 'approved']);

    $this->artisan('phones:export-catalogue', ['--brand' => 'noidleakbrand'])->assertExitCode(0);

    $forbiddenKeys = ['id', 'phone_id', 'phone_variant_id', 'store_id', 'source_id', 'brand_id', 'created_by', 'updated_by'];

    $assertNoForbiddenKeys = function (array $data) use (&$assertNoForbiddenKeys, $forbiddenKeys) {
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                expect($forbiddenKeys)->not->toContain($key, "Exported payload leaked raw identifier key '{$key}'.");
            }

            if (is_array($value)) {
                $assertNoForbiddenKeys($value);
            }
        }
    };

    $payload = json_decode(File::get("{$this->directory}/noidleakbrand.json"), true);
    $assertNoForbiddenKeys($payload);
});

it('re-sorts stores.json alphabetically instead of leaving it in database insertion order', function () {
    PhoneStore::factory()->create(['slug' => 'zzz-store', 'name' => 'ZZZ Store']);
    PhoneStore::factory()->create(['slug' => 'aaa-store', 'name' => 'AAA Store']);
    Brand::factory()->create(['slug' => 'anybrand']);

    $this->artisan('phones:export-catalogue')->assertExitCode(0);

    $payload = json_decode(File::get("{$this->directory}/stores.json"), true);
    expect(collect($payload['stores'])->pluck('slug')->all())->toBe(['aaa-store', 'zzz-store']);
});
