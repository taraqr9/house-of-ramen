<?php

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneSpec;
use App\Services\PhoneImport\DuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('treats an identical name as a confident match', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    Phone::factory()->for($brand)->create(['name' => 'Galaxy S24 Ultra', 'slug' => 'samsung-galaxy-s24-ultra']);

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Galaxy S24 Ultra');

    expect($detector->classify($best['score']))->toBe('matched');
});

it('flags a name that only differs by a marketing prefix or 5G suffix as a possible duplicate', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    Phone::factory()->for($brand)->create(['name' => 'Galaxy A56', 'slug' => 'samsung-galaxy-a56']);

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'A56 5G');

    expect($detector->classify($best['score']))->toBe('needs_review');
});

it('does not treat a Pro/Ultra tier variant as a duplicate of the base model', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    Phone::factory()->for($brand)->create(['name' => 'Galaxy S24', 'slug' => 'samsung-galaxy-s24']);

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Galaxy S24 Ultra');

    expect($detector->classify($best['score']))->toBe('new');
});

it('does not match an unrelated model in the same brand', function () {
    $brand = Brand::factory()->create(['name' => 'Xiaomi', 'slug' => 'xiaomi']);
    Phone::factory()->for($brand)->create(['name' => 'Xiaomi 14', 'slug' => 'xiaomi-14']);

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Redmi Note 13');

    expect($detector->classify($best['score']))->toBe('new');
});

it('does not treat a "Pro+" variant as a duplicate of the plain "Pro" model', function () {
    // Regression: "+" used to be silently stripped as punctuation, so
    // "Note 13 Pro" and "Note 13 Pro+" both canonicalized to the exact
    // same string and were auto-merged as one phone - discovered when
    // expanding the seeded catalogue produced this exact real-world pair.
    $brand = Brand::factory()->create(['name' => 'Redmi', 'slug' => 'redmi']);
    Phone::factory()->for($brand)->create(['name' => 'Redmi Note 14 Pro', 'slug' => 'redmi-redmi-note-14-pro']);

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Redmi Note 14 Pro+');

    expect($detector->classify($best['score']))->toBe('new');
});

// -----------------------------------------------------------------------
// Spec-corroboration regression coverage.
//
// A brand-specific model-line word ("Note", "Power", "Premier", "Edge",
// "Nord", ...) can't be caught by a hard-coded suffix list - every brand
// mints new ones constantly, and "Redmi 12" vs "Redmi Note 12" looks
// exactly as name-similar as a harmless naming variant of the same
// phone. Discovered when expanding the seeded catalogue produced these
// exact real-world pairs (genuinely different phones, silently flagged
// as possible duplicates and dropped from the catalogue). Fixed by
// corroborating an ambiguous name match against the phones' actual
// hardware instead of growing the word list further.
// -----------------------------------------------------------------------

it('treats a same-name-family phone as a different model when its specs genuinely diverge (no word list involved)', function () {
    $brand = Brand::factory()->create(['name' => 'Redmi', 'slug' => 'redmi']);
    $existing = Phone::factory()->for($brand)->create(['name' => 'Redmi 12', 'slug' => 'redmi-redmi-12']);
    PhoneSpec::factory()->for($existing)->create([
        'processor' => 'MediaTek Helio G88', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 18, 'main_camera' => '50 MP, f/1.8',
    ]);

    $detector = new DuplicateDetector;
    $incomingSpecs = ['processor' => 'Qualcomm Snapdragon 685', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 33, 'main_camera' => '50 MP, f/1.8, OIS'];
    $best = $detector->findBestMatch($brand, 'Redmi Note 12', $incomingSpecs);

    expect($detector->classify($best['score']))->toBe('new');
});

it('treats a battery-boosted named variant as a different model when only the battery genuinely diverges', function () {
    // Isolates a single diverging field (same chipset/camera, meaningfully
    // bigger battery only) - mirrors the real "Moto G54" vs "Moto G54
    // Power" pair, where a single strong signal is the only thing that
    // tells the two phones apart.
    $brand = Brand::factory()->create(['name' => 'Motorola', 'slug' => 'motorola']);
    $existing = Phone::factory()->for($brand)->create(['name' => 'Moto G54', 'slug' => 'motorola-moto-g54']);
    PhoneSpec::factory()->for($existing)->create([
        'processor' => 'MediaTek Dimensity 7020', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 33, 'main_camera' => '50 MP, f/1.89, OIS',
    ]);

    $detector = new DuplicateDetector;
    $incomingSpecs = ['processor' => 'MediaTek Dimensity 7020', 'battery_capacity_mah' => 6000, 'charging_speed_w' => 33, 'main_camera' => '50 MP, f/1.89, OIS'];
    $best = $detector->findBestMatch($brand, 'Moto G54 Power', $incomingSpecs);

    expect($detector->classify($best['score']))->toBe('new');
});

it('still treats a same-name-family phone as a possible duplicate when its specs genuinely agree', function () {
    // The other half of the mechanism: an ambiguous name match whose
    // hardware actually DOES line up (the same phone, just listed under
    // a slightly different name) should stay in the "needs review" band
    // rather than being forced apart just because the names differ.
    $brand = Brand::factory()->create(['name' => 'Redmi', 'slug' => 'redmi']);
    $existing = Phone::factory()->for($brand)->create(['name' => 'Redmi 12', 'slug' => 'redmi-redmi-12']);
    PhoneSpec::factory()->for($existing)->create([
        'processor' => 'MediaTek Helio G88', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 18, 'main_camera' => '50 MP, f/1.8',
    ]);

    $detector = new DuplicateDetector;
    $incomingSpecs = ['processor' => 'MediaTek Helio G88', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 18, 'main_camera' => '50 MP, f/1.8'];
    $best = $detector->findBestMatch($brand, 'Redmi Note 12', $incomingSpecs);

    expect($detector->classify($best['score']))->toBe('needs_review');
});

it('falls back to the name-only verdict when spec data is not available to corroborate', function () {
    $brand = Brand::factory()->create(['name' => 'Redmi', 'slug' => 'redmi']);
    Phone::factory()->for($brand)->create(['name' => 'Redmi 12', 'slug' => 'redmi-redmi-12']);

    $detector = new DuplicateDetector;
    $best = $detector->findBestMatch($brand, 'Redmi Note 12');

    expect($detector->classify($best['score']))->toBe('needs_review');
});

it('treats a 5G suffix as ambiguous by name alone but resolves it as a different phone when the chipset diverges', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    $existing = Phone::factory()->for($brand)->create(['name' => 'Galaxy A16', 'slug' => 'samsung-galaxy-a16']);
    PhoneSpec::factory()->for($existing)->create(['processor' => 'MediaTek Helio G99', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 25]);

    $detector = new DuplicateDetector;
    $incomingSpecs = ['processor' => 'MediaTek Dimensity 6100+', 'battery_capacity_mah' => 5000, 'charging_speed_w' => 25];
    $best = $detector->findBestMatch($brand, 'Galaxy A16 5G', $incomingSpecs);

    expect($detector->classify($best['score']))->toBe('new');
});
