<?php

/**
 * Final adversarial validation pass for phone-identity matching before
 * the phone-data foundation is considered stable. Each case below is a
 * hard real-world naming/spec scenario, classified into one of three
 * buckets and asserted against the actual DuplicateDetector output:
 *
 *   - Duplicate     -> classify() === 'matched'       (safe to merge)
 *   - Review        -> classify() === 'needs_review'  (ambiguous, human decides)
 *   - Different      -> classify() === 'new'           (must stay separate)
 *
 * A false "Duplicate" is the dangerous failure mode (silently corrupts
 * the catalogue by merging two different phones), so every case that
 * should NOT auto-merge is asserted precisely - not just "not matched".
 */

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneSpec;
use App\Services\PhoneImport\DuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function seedExisting(Brand $brand, string $name, ?array $specs = null): Phone
{
    $phone = Phone::factory()->for($brand)->create(['name' => $name]);

    if ($specs !== null) {
        PhoneSpec::factory()->for($phone)->create($specs);
    }

    return $phone;
}

function outcome(DuplicateDetector $detector, ?array $best): string
{
    return $best === null ? 'new' : $detector->classify($best['score']);
}

$fullSpec = fn (array $overrides = []) => array_merge([
    'processor' => 'Qualcomm Snapdragon 685', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 33, 'main_camera' => '50 MP, f/1.8, OIS',
], $overrides);

// 1. Same phone, slightly different naming (case + whitespace) -> Duplicate
it('[case 1] merges the same phone listed with different case and spacing', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Apple']);
    seedExisting($brand, 'iPhone 15', $fullSpec());

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'iphone   15', $fullSpec());

    expect(outcome($detector, $best))->toBe('matched');
});

// 2. Same phone with 4G/5G naming differences (matching hardware) -> Review
it('[case 2] flags a bare 4G/5G naming difference as review, not an outright match, when hardware matches', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Samsung']);
    seedExisting($brand, 'Galaxy F55', $fullSpec());

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Galaxy F55 5G', $fullSpec());

    expect(outcome($detector, $best))->toBe('needs_review');
});

// 3. Same phone with regional naming differences (matching hardware) -> Review
it('[case 3] flags a regional naming suffix as review, not new, when the hardware behind it matches', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Redmi']);
    seedExisting($brand, 'Redmi Note 13', $fullSpec());

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Redmi Note 13 Global', $fullSpec());

    expect(outcome($detector, $best))->toBe('needs_review');
});

// 5. Pro vs Pro+ -> Different
it('[case 5] treats Pro vs Pro+ as different phones regardless of spec similarity', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Redmi']);
    seedExisting($brand, 'Redmi Note 14 Pro', $fullSpec());

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Redmi Note 14 Pro+', $fullSpec());

    expect(outcome($detector, $best))->toBe('new');
});

// 6. Pro vs Pro Max -> Different
it('[case 6] treats Pro vs Pro Max as different phones', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Apple']);
    seedExisting($brand, 'iPhone 15 Pro', $fullSpec());

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'iPhone 15 Pro Max', $fullSpec());

    expect(outcome($detector, $best))->toBe('new');
});

// 7. Plus vs non-Plus -> Different
it('[case 7] treats Plus vs non-Plus as different phones', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Apple']);
    seedExisting($brand, 'iPhone 14', $fullSpec());

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'iPhone 14 Plus', $fullSpec());

    expect(outcome($detector, $best))->toBe('new');
});

// 8. Ultra vs non-Ultra -> Different
it('[case 8] treats Ultra vs non-Ultra as different phones', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Samsung']);
    seedExisting($brand, 'Galaxy S23', $fullSpec());

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Galaxy S23 Ultra', $fullSpec());

    expect(outcome($detector, $best))->toBe('new');
});

// 9. Redmi model vs Redmi Note model -> Different
it('[case 9] treats a base "Redmi" model as different from the "Redmi Note" model it is named after', function () {
    $brand = Brand::factory()->create(['name' => 'Redmi']);
    seedExisting($brand, 'Redmi 13', [
        'processor' => 'MediaTek Helio G91-Ultra', 'battery_capacity_mah' => 5030, 'charging_speed_w' => 33, 'main_camera' => '108 MP, f/1.7',
    ]);

    $detector = new DuplicateDetector;
    $incoming = ['processor' => 'Qualcomm Snapdragon 685', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 33, 'main_camera' => '108 MP, f/1.7, OIS'];
    $best = $detector->findBestMatch($brand, 'Redmi Note 13', $incoming);

    expect(outcome($detector, $best))->toBe('new');
});

// 11. Different generations with similar names -> Different
it('[case 11] treats consecutive generations with a similar name as different phones', function () {
    $brand = Brand::factory()->create(['name' => 'Redmi']);
    seedExisting($brand, 'Redmi Note 13', [
        'processor' => 'Qualcomm Snapdragon 685', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 33, 'main_camera' => '108 MP, f/1.7, OIS',
    ]);

    $detector = new DuplicateDetector;
    $incoming = ['processor' => 'MediaTek Dimensity 7300-Ultra', 'battery_capacity_mah' => 5500, 'charging_speed_w' => 45, 'main_camera' => '200 MP, f/1.65, OIS'];
    $best = $detector->findBestMatch($brand, 'Redmi Note 14', $incoming);

    expect(outcome($detector, $best))->toBe('new');
});

// 12. Different phones with very similar specifications -> Different
// (Proves specs alone can never promote a clearly-unrelated name pair
// into a match/review - hardware only ever corroborates an ALREADY
// ambiguous name signal, it never substitutes for one.)
it('[case 12] keeps two unrelated model names different even when their hardware is nearly identical', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Redmi']);
    seedExisting($brand, 'Redmi 12', $fullSpec());

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Redmi A3', $fullSpec());

    expect(outcome($detector, $best))->toBe('new');
});

// 13. Missing specifications -> falls back to the name-only verdict, never crashes
it('[case 13] falls back to the name-only verdict without crashing when the existing phone has no spec row', function () {
    $brand = Brand::factory()->create(['name' => 'Redmi']);
    seedExisting($brand, 'Redmi 12', null); // no spec row at all

    $detector = new DuplicateDetector;
    $incoming = ['processor' => 'Qualcomm Snapdragon 685', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 33, 'main_camera' => '50 MP, f/1.8, OIS'];
    $best = $detector->findBestMatch($brand, 'Redmi Note 12', $incoming);

    expect(outcome($detector, $best))->toBe('needs_review');
});

it('[case 13b] falls back to the name-only verdict without crashing when the incoming record has no specs at all', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Redmi']);
    seedExisting($brand, 'Redmi 12', $fullSpec());

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Redmi Note 12', null);

    expect(outcome($detector, $best))->toBe('needs_review');
});

// 14. Slightly different battery values caused by source rounding -> tolerated, stays Review
it('[case 14] tolerates a small battery rounding difference between sources instead of treating it as a different phone', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung']);
    seedExisting($brand, 'Galaxy F55', [
        'processor' => 'MediaTek Dimensity 7300', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 45, 'main_camera' => '50 MP, f/1.8, OIS',
    ]);

    $detector = new DuplicateDetector;
    // 5015 vs 5000 is a 0.3% difference - source rounding noise, not a real spec.
    $incoming = ['processor' => 'MediaTek Dimensity 7300', 'battery_capacity_mah' => 5015, 'charging_speed_w' => 45, 'main_camera' => '50 MP, f/1.8, OIS'];
    $best = $detector->findBestMatch($brand, 'Galaxy F55 5G', $incoming);

    expect(outcome($detector, $best))->toBe('needs_review');
});

// 15. Slightly different camera value formatting -> tolerated, stays Review
it('[case 15] tolerates a differently-formatted but equal camera spec instead of treating it as a different phone', function () {
    $brand = Brand::factory()->create(['name' => 'Redmi']);
    seedExisting($brand, 'Redmi Note 13', [
        'processor' => 'Qualcomm Snapdragon 685', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 33, 'main_camera' => '50 MP, f/1.8, OIS',
    ]);

    $detector = new DuplicateDetector;
    // Same 50MP sensor, just formatted differently by another source.
    $incoming = ['processor' => 'Qualcomm Snapdragon 685', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 33, 'main_camera' => '50MP f/1.8 OIS main sensor'];
    $best = $detector->findBestMatch($brand, 'Redmi Note 13 Global', $incoming);

    expect(outcome($detector, $best))->toBe('needs_review');
});

// 16. Different charging speeds -> Different
it('[case 16] treats a meaningfully different charging speed as a different phone', function () {
    $brand = Brand::factory()->create(['name' => 'Tecno']);
    seedExisting($brand, 'Tecno Spark 30', [
        'processor' => 'MediaTek Helio G85', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 18, 'main_camera' => '50 MP, f/1.8',
    ]);

    $detector = new DuplicateDetector;
    $incoming = ['processor' => 'MediaTek Helio G85', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 33, 'main_camera' => '50 MP, f/1.8'];
    $best = $detector->findBestMatch($brand, 'Tecno Spark 30 Prime', $incoming);

    expect(outcome($detector, $best))->toBe('new');
});

// 17. Different processors -> Different
it('[case 17] treats a different chipset alone as enough to be a different phone', function () {
    $brand = Brand::factory()->create(['name' => 'Vivo']);
    seedExisting($brand, 'Vivo Y18', [
        'processor' => 'MediaTek Helio G85', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 15, 'main_camera' => '13 MP, f/2.2',
    ]);

    $detector = new DuplicateDetector;
    $incoming = ['processor' => 'Unisoc T612', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 15, 'main_camera' => '13 MP, f/2.2'];
    $best = $detector->findBestMatch($brand, 'Vivo Y18 Prime', $incoming);

    expect(outcome($detector, $best))->toBe('new');
});

// 18. 4G vs 5G versions that are genuinely different -> Different
it('[case 18] treats a 4G/5G naming difference as a different phone when the chipset behind it genuinely diverges', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung']);
    seedExisting($brand, 'Galaxy A16', [
        'processor' => 'MediaTek Helio G99', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 25, 'main_camera' => '50 MP, f/1.8, OIS',
    ]);

    $detector = new DuplicateDetector;
    $incoming = ['processor' => 'MediaTek Dimensity 6100+', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 25, 'main_camera' => '50 MP, f/1.8, OIS'];
    $best = $detector->findBestMatch($brand, 'Galaxy A16 5G', $incoming);

    expect(outcome($detector, $best))->toBe('new');
});

// 19. Regional variants that should remain separate -> Different
it('[case 19] keeps a region-tagged listing separate when its actual configuration genuinely diverges', function () {
    $brand = Brand::factory()->create(['name' => 'Xiaomi']);
    seedExisting($brand, 'Xiaomi 14', [
        'processor' => 'Qualcomm Snapdragon 8 Gen 3', 'battery_capacity_mah' => 4610, 'charging_speed_w' => 90, 'main_camera' => '50 MP, f/1.6, OIS',
    ]);

    $detector = new DuplicateDetector;
    // A regionally-certified charger cap genuinely differs from the global unit.
    $incoming = ['processor' => 'Qualcomm Snapdragon 8 Gen 3', 'battery_capacity_mah' => 4610, 'charging_speed_w' => 120, 'main_camera' => '50 MP, f/1.6, OIS'];
    $best = $detector->findBestMatch($brand, 'Xiaomi 14 China', $incoming);

    expect(outcome($detector, $best))->toBe('new');
});

// 20. Exact duplicate listings that should merge -> Duplicate
it('[case 20] merges an exact duplicate listing (same name, same hardware) from another source', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Samsung']);
    seedExisting($brand, 'Galaxy A16 5G', $fullSpec());

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Galaxy A16 5G', $fullSpec());

    expect(outcome($detector, $best))->toBe('matched');
});

// -----------------------------------------------------------------------
// Efficiency: findBestMatch must stay O(1) database queries regardless of
// catalogue size (eager-loaded spec relation, no N+1), so duplicate
// detection doesn't degrade as the catalogue grows well past 200 phones.
// -----------------------------------------------------------------------
it('runs findBestMatch in a small, constant number of queries against a 250-phone brand catalogue', function () use ($fullSpec) {
    $brand = Brand::factory()->create(['name' => 'Samsung']);

    Phone::factory()->for($brand)->count(250)->create()->each(function (Phone $phone) {
        PhoneSpec::factory()->for($phone)->create();
    });

    $detector = new DuplicateDetector;

    DB::enableQueryLog();
    $detector->findBestMatch($brand, 'Galaxy Totally New Model', $fullSpec());
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(3);
});
