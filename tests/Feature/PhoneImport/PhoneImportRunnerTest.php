<?php

use App\Enums\ImportRunStatusEnum;
use App\Enums\ImportRunTypeEnum;
use App\Enums\SourceTypeEnum;
use App\Models\Phone;
use App\Models\PhoneDataConflict;
use App\Models\PhoneDataReview;
use App\Models\PhoneImportRun;
use App\Models\PhonePriceHistory;
use App\Models\PhoneSource;
use App\Services\PhoneImport\Contracts\PhoneSourceProvider;
use App\Services\PhoneImport\PhoneImportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Simple in-memory provider for exercising the runner without a real source.
 */
class FakeArrayPhoneSource implements PhoneSourceProvider
{
    public function __construct(
        private array $items,
        private string $sourceKey = 'fake_source',
        private SourceTypeEnum $sourceType = SourceTypeEnum::MANUAL,
    ) {}

    public function key(): string
    {
        return $this->sourceKey;
    }

    public function type(): SourceTypeEnum
    {
        return $this->sourceType;
    }

    public function fetchBatch(?array $cursor, int $limit): array
    {
        $offset = $cursor['offset'] ?? 0;
        $batch = array_slice($this->items, $offset, $limit);
        $next = $offset + count($batch);

        return ['items' => $batch, 'cursor' => ['offset' => $next], 'done' => $next >= count($this->items)];
    }
}

/**
 * Throws once when it first reaches a given cursor offset, then behaves
 * normally - simulates a transient failure that a resumed run recovers from.
 */
class FlakyArrayPhoneSource extends FakeArrayPhoneSource
{
    private bool $hasThrown = false;

    public function __construct(array $items, private int $throwAtOffset)
    {
        parent::__construct($items);
    }

    public function fetchBatch(?array $cursor, int $limit): array
    {
        $offset = $cursor['offset'] ?? 0;

        if ($offset === $this->throwAtOffset && ! $this->hasThrown) {
            $this->hasThrown = true;

            throw new RuntimeException('Simulated transient failure');
        }

        return parent::fetchBatch($cursor, $limit);
    }
}

function makePhoneRaw(string $model, array $overrides = []): array
{
    return array_merge([
        'external_ref' => Str::slug('samsung '.$model),
        'brand' => 'Samsung',
        'model' => $model,
        'announced_date' => '2024-01-01',
        'release_date' => '2024-01-15',
        'status' => 'available',
        'category' => 'midrange',
        'specs' => [
            'processor' => 'Test Chipset',
            'display_size' => '6.5',
            'battery_capacity' => '5000 mAh',
            'charging_speed' => '25W',
        ],
        'variants' => [
            [
                'ram' => '8 GB', 'storage' => '128 GB', 'color' => 'Black', 'region' => 'Global',
                'is_official_bd' => true, 'price' => ['official_bd' => '29,999'], 'availability' => 'in_stock', 'store' => 'Test Store',
            ],
        ],
    ], $overrides);
}

it('imports a brand new phone end to end with correct normalized values', function () {
    $source = PhoneSource::factory()->create(['type' => SourceTypeEnum::MANUFACTURER, 'reliability_score' => 95, 'requires_review' => false]);
    $provider = new FakeArrayPhoneSource([makePhoneRaw('Galaxy Test One')]);

    $runner = app(PhoneImportRunner::class);
    $run = $runner->run($provider, $source, ImportRunTypeEnum::MANUAL);

    expect($run->status)->toBe(ImportRunStatusEnum::COMPLETED)
        ->and($run->total_created)->toBe(1);

    $phone = Phone::query()->where('slug', 'samsung-galaxy-test-one')->firstOrFail();

    expect($phone->spec->battery_capacity_mah)->toBe(5000)
        ->and($phone->spec->display_size)->toEqual('6.5')
        ->and($phone->variants()->first()->ram_gb)->toBe(8)
        ->and($phone->variants()->first()->prices()->first()->amount)->toEqual('29999.00')
        ->and($phone->overall_confidence)->toBeGreaterThan(0);
});

it('never doubles the brand name in the slug when the source model already includes it', function () {
    // Real bug found in production data: several sources give a "model"
    // that already contains the brand (brand "Redmi" / model "Redmi Note
    // Test One"), and the importer used to concatenate both unconditionally,
    // producing "redmi-redmi-note-test-one" for 157 of 220 catalogue phones.
    $source = PhoneSource::factory()->create(['type' => SourceTypeEnum::MANUFACTURER, 'reliability_score' => 95, 'requires_review' => false]);
    $provider = new FakeArrayPhoneSource([makePhoneRaw('Redmi Note Test One', ['brand' => 'Redmi'])]);

    $runner = app(PhoneImportRunner::class);
    $runner->run($provider, $source, ImportRunTypeEnum::MANUAL);

    expect(Phone::query()->where('slug', 'redmi-note-test-one')->exists())->toBeTrue()
        ->and(Phone::query()->where('slug', 'redmi-redmi-note-test-one')->exists())->toBeFalse();
});

it('only records price history when the price actually changes', function () {
    $source = PhoneSource::factory()->create(['reliability_score' => 90]);

    $runner = app(PhoneImportRunner::class);

    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Test Two')]), $source, ImportRunTypeEnum::MANUAL);
    expect(PhonePriceHistory::count())->toBe(1);

    // Re-import with the identical price - no new history row.
    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Test Two')]), $source, ImportRunTypeEnum::NIGHTLY);
    expect(PhonePriceHistory::count())->toBe(1);

    // Re-import with a changed price - exactly one new history row.
    $changed = makePhoneRaw('Galaxy Test Two');
    $changed['variants'][0]['price']['official_bd'] = '27,999';
    $runner->run(new FakeArrayPhoneSource([$changed]), $source, ImportRunTypeEnum::NIGHTLY);

    expect(PhonePriceHistory::count())->toBe(2);
    $latest = PhonePriceHistory::query()->latest('id')->first();
    expect($latest->amount)->toEqual('27999.00')->and($latest->previous_amount)->toEqual('29999.00');
});

it('flags a conflicting spec value for review instead of silently overwriting it', function () {
    $strongSource = PhoneSource::factory()->create(['type' => SourceTypeEnum::MANUFACTURER, 'reliability_score' => 90]);
    $weakerSource = PhoneSource::factory()->create(['type' => SourceTypeEnum::AI_ASSISTED, 'reliability_score' => 92]);

    $runner = app(PhoneImportRunner::class);

    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Test Three')]), $strongSource, ImportRunTypeEnum::MANUAL);

    $conflicting = makePhoneRaw('Galaxy Test Three');
    $conflicting['specs']['battery_capacity'] = '5100 mAh';
    $runner->run(new FakeArrayPhoneSource([$conflicting]), $weakerSource, ImportRunTypeEnum::MANUAL);

    $phone = Phone::query()->where('slug', 'samsung-galaxy-test-three')->firstOrFail();

    // Reliability gap (92 - 90 = 2) is below the auto-resolve margin, so the
    // existing value must be preserved and a conflict flagged for review.
    expect($phone->spec->battery_capacity_mah)->toBe(5000);

    $conflict = PhoneDataConflict::query()->where('phone_id', $phone->id)->where('field', 'battery_capacity_mah')->firstOrFail();
    expect($conflict->status->value)->toBe('open')
        ->and($conflict->existing_value)->toBe('5000')
        ->and($conflict->new_value)->toBe('5100');
});

it('auto-resolves a conflict when the new source is meaningfully more reliable', function () {
    $weakSource = PhoneSource::factory()->create(['type' => SourceTypeEnum::AI_ASSISTED, 'reliability_score' => 45]);
    $strongSource = PhoneSource::factory()->create(['type' => SourceTypeEnum::MANUFACTURER, 'reliability_score' => 95]);

    $runner = app(PhoneImportRunner::class);

    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Test Four')]), $weakSource, ImportRunTypeEnum::MANUAL);

    $corrected = makePhoneRaw('Galaxy Test Four');
    $corrected['specs']['battery_capacity'] = '5100 mAh';
    $runner->run(new FakeArrayPhoneSource([$corrected]), $strongSource, ImportRunTypeEnum::MANUAL);

    $phone = Phone::query()->where('slug', 'samsung-galaxy-test-four')->firstOrFail();

    expect($phone->spec->battery_capacity_mah)->toBe(5100);

    $conflict = PhoneDataConflict::query()->where('phone_id', $phone->id)->where('field', 'battery_capacity_mah')->firstOrFail();
    expect($conflict->status->value)->toBe('auto_resolved');
});

it('does not degrade an existing phone\'s identity confidence when a later price-only retailer update supplies no identity fields', function () {
    $strongSource = PhoneSource::factory()->create(['reliability_score' => 90]);
    $weakRetailerSource = PhoneSource::factory()->create(['reliability_score' => 80]);

    $runner = app(PhoneImportRunner::class);

    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Test Six')]), $strongSource, ImportRunTypeEnum::MANUAL);

    $phone = Phone::query()->where('slug', 'samsung-galaxy-test-six')->firstOrFail();
    $identityBefore = $phone->identity_confidence;
    expect($identityBefore)->toBeGreaterThan(0);

    // Shaped exactly like RetailerListingSource::buildRawRecord() - only
    // brand/model/variants, no announced_date/release_date/status/specs.
    // Before the fix, this silently dragged identity_confidence down to
    // whatever this weaker source's reliability implied, even though it
    // never touched a single identity field.
    $priceOnly = makePhoneRaw('Galaxy Test Six', [
        'announced_date' => null, 'release_date' => null, 'status' => null, 'category' => null, 'specs' => [],
    ]);
    $runner->run(new FakeArrayPhoneSource([$priceOnly]), $weakRetailerSource, ImportRunTypeEnum::MANUAL);

    expect($phone->fresh()->identity_confidence)->toBe($identityBefore);
});

// -----------------------------------------------------------------------
// Adversarial validation: two cases from the phone-identity matching
// pass that need the full pipeline (variant association, conflict
// detection) rather than DuplicateDetector alone. See
// DuplicateDetectionAdversarialTest.php for the 18 name/spec-only cases.
// -----------------------------------------------------------------------

it('[case 4] merges a RAM/storage variant of the same phone into the same phone row instead of creating a second phone', function () {
    $source = PhoneSource::factory()->create(['reliability_score' => 90]);
    $runner = app(PhoneImportRunner::class);

    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Test Variant')]), $source, ImportRunTypeEnum::MANUAL);

    $secondVariant = makePhoneRaw('Galaxy Test Variant');
    $secondVariant['variants'][0] = [
        'ram' => '12 GB', 'storage' => '256 GB', 'color' => 'Black', 'region' => 'Global',
        'is_official_bd' => true, 'price' => ['official_bd' => '34,999'], 'availability' => 'in_stock', 'store' => 'Test Store',
    ];
    $runner->run(new FakeArrayPhoneSource([$secondVariant]), $source, ImportRunTypeEnum::MANUAL);

    $phone = Phone::query()->where('slug', 'samsung-galaxy-test-variant')->firstOrFail();

    expect(Phone::count())->toBe(1)
        ->and($phone->variants()->count())->toBe(2)
        ->and($phone->variants()->pluck('storage_gb')->sort()->values()->all())->toBe([128, 256]);
});

it('[case 10] never silently corrupts an existing phone when a same-name listing reports genuinely different hardware', function () {
    // The hardest case: two real phones sharing an identical marketing
    // name (e.g. a regional chipset split sold under one name) should
    // never result in silent data corruption - even though name identity
    // alone is treated as a confident match, a conflicting watched spec
    // field must still be preserved and flagged, never blindly
    // overwritten by whichever record happened to import second.
    $source = PhoneSource::factory()->create(['reliability_score' => 90]);
    $runner = app(PhoneImportRunner::class);

    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Test Five', [
        'specs' => ['processor' => 'Exynos 2400', 'display_size' => '6.8', 'battery_capacity' => '5000 mAh', 'charging_speed' => '45W'],
    ])]), $source, ImportRunTypeEnum::MANUAL);

    $genuinelyDifferentHardware = makePhoneRaw('Galaxy Test Five', [
        'specs' => ['processor' => 'Qualcomm Snapdragon 8 Gen 3', 'display_size' => '6.8', 'battery_capacity' => '5000 mAh', 'charging_speed' => '45W'],
    ]);
    $runner->run(new FakeArrayPhoneSource([$genuinelyDifferentHardware]), $source, ImportRunTypeEnum::MANUAL);

    $phone = Phone::query()->where('slug', 'samsung-galaxy-test-five')->firstOrFail();

    // Still one catalogue row (name-identity match) - but the conflicting
    // field was NOT silently overwritten by the second import.
    expect(Phone::count())->toBe(1)
        ->and($phone->spec->processor)->toBe('Exynos 2400');

    $conflict = PhoneDataConflict::query()->where('phone_id', $phone->id)->where('field', 'processor')->first();
    expect($conflict)->not->toBeNull()
        ->and($conflict->status->value)->toBe('open')
        ->and($conflict->existing_value)->toBe('Exynos 2400')
        ->and($conflict->new_value)->toBe('Qualcomm Snapdragon 8 Gen 3');
});

it('never stacks up duplicate possible_duplicate reviews when the same ambiguous record is re-imported (e.g. a --fresh re-run)', function () {
    $source = PhoneSource::factory()->create(['reliability_score' => 90, 'requires_review' => false]);
    $runner = app(PhoneImportRunner::class);

    // The phone the ambiguous incoming record will be scored against.
    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Redmi 12', ['brand' => 'Redmi', 'specs' => []])]), $source, ImportRunTypeEnum::MANUAL);

    // "Redmi Note 12" is a token-subset of "Redmi 12" with no specs to
    // corroborate either way - lands squarely in the needs_review band
    // (see DuplicateDetectionAdversarialTest case 13b for the same fixture).
    $ambiguous = makePhoneRaw('Redmi Note 12', ['brand' => 'Redmi', 'specs' => []]);

    // Same raw record processed three times, as a repeated/--fresh import
    // pass would - before the fix, each pass created its own review row.
    $runner->run(new FakeArrayPhoneSource([$ambiguous]), $source, ImportRunTypeEnum::MANUAL);
    $runner->run(new FakeArrayPhoneSource([$ambiguous]), $source, ImportRunTypeEnum::MANUAL);
    $runner->run(new FakeArrayPhoneSource([$ambiguous]), $source, ImportRunTypeEnum::MANUAL);

    expect(PhoneDataReview::query()->where('reason', 'possible_duplicate')->count())->toBe(1);
});

it('never re-queues a low_confidence review for a phone once a human/agent has already decided it - even after the review was resolved, not just while pending', function () {
    // A requires_review source (e.g. seed_dataset) whose reliability alone
    // can never clear the auto-approve bar, so every reimport of the same
    // phone would otherwise re-flag it forever.
    $weakSource = PhoneSource::factory()->create(['reliability_score' => 55, 'requires_review' => true]);
    $runner = app(PhoneImportRunner::class);

    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Weak One')]), $weakSource, ImportRunTypeEnum::MANUAL);

    $phone = Phone::query()->where('slug', 'samsung-galaxy-weak-one')->firstOrFail();
    $review = PhoneDataReview::where('phone_id', $phone->id)->where('reason', 'low_confidence')->firstOrFail();

    // A human/agent looked at it and rejected it (invalid/duplicate/unsupported).
    $review->update(['status' => 'rejected', 'reviewed_at' => now()]);

    // The exact same source reimports the exact same phone again (e.g. a
    // --fresh nightly rerun) - before the fix, this created a second
    // low_confidence review since the dedup guard only checked for a
    // still-PENDING row.
    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Weak One')]), $weakSource, ImportRunTypeEnum::MANUAL);
    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Weak One')]), $weakSource, ImportRunTypeEnum::MANUAL);

    expect(PhoneDataReview::query()->where('phone_id', $phone->id)->where('reason', 'low_confidence')->count())->toBe(1)
        ->and($review->fresh()->status->value)->toBe('rejected');
});

it('resumes an interrupted run from its saved checkpoint without reprocessing completed items', function () {
    $source = PhoneSource::factory()->create(['reliability_score' => 90]);

    $items = [makePhoneRaw('Galaxy Resume One'), makePhoneRaw('Galaxy Resume Two'), makePhoneRaw('Galaxy Resume Three')];
    $flakyProvider = new FlakyArrayPhoneSource($items, throwAtOffset: 1);

    $runner = app(PhoneImportRunner::class);

    try {
        $runner->run($flakyProvider, $source, ImportRunTypeEnum::INITIAL, chunkSize: 1);
        $this->fail('Expected the flaky provider to throw on the second batch.');
    } catch (RuntimeException) {
        // expected
    }

    $run = PhoneImportRun::query()->where('source_id', $source->id)->firstOrFail();
    expect($run->status)->toBe(ImportRunStatusEnum::PARTIAL)
        ->and(Phone::count())->toBe(1); // only the first item made it through before the failure

    $resumed = $runner->run($flakyProvider, $source, ImportRunTypeEnum::INITIAL, resumeRun: $run, chunkSize: 1);

    expect($resumed->id)->toBe($run->id)
        ->and($resumed->status)->toBe(ImportRunStatusEnum::COMPLETED)
        ->and(Phone::count())->toBe(3)
        ->and($resumed->total_created)->toBe(3);
});

it('imports several retailer observations for one listing, computes the market price from them, and routes the outlier to review instead of corrupting it', function () {
    $source = PhoneSource::factory()->create(['reliability_score' => 90]);
    $runner = app(PhoneImportRunner::class);

    $raw = makePhoneRaw('Galaxy Multi Retailer', [
        'variants' => [[
            'ram' => '12 GB', 'storage' => '256 GB', 'color' => 'Black', 'region' => 'Global',
            'is_official_bd' => true,
            'price' => [
                'official_bd' => '1,79,999',
                'unofficial_bd' => [
                    ['amount' => '1,54,000', 'store' => 'Rio International'],
                    ['amount' => '1,56,000', 'store' => 'Sumash Tech'],
                    ['amount' => '1,58,000', 'store' => 'Dazzle'],
                    ['amount' => '80,000', 'store' => 'Suspicious Shop'],
                ],
            ],
            'availability' => 'in_stock', 'store' => 'Samsung Bangladesh',
        ]],
    ]);

    $run = $runner->run(new FakeArrayPhoneSource([$raw]), $source, ImportRunTypeEnum::MANUAL);

    expect($run->total_created)->toBe(1);

    $phone = Phone::query()->where('slug', 'samsung-galaxy-multi-retailer')->firstOrFail();
    $variant = $phone->variants()->firstOrFail();

    // All four raw observations are preserved (nothing silently dropped).
    expect($variant->prices()->where('price_type', 'unofficial_bd')->count())->toBe(4);

    $officialMarket = $variant->marketPrices()->where('price_type', 'official_bd')->firstOrFail();
    $unofficialMarket = $variant->marketPrices()->where('price_type', 'unofficial_bd')->firstOrFail();

    expect((float) $officialMarket->price)->toBe(179999.0)
        ->and((float) $unofficialMarket->price)->toBe(156000.0) // median of the three clustered retailers
        ->and($unofficialMarket->retailer_count)->toBe(3) // the outlier's retailer excluded from the count
        ->and($unofficialMarket->outlier_count)->toBe(1);
});

it('rejects an implausible price on a brand-new listing even with no existing market price to compare against', function () {
    // Real gap found in production: isSane() had nothing to check a
    // phone's very FIRST price observation against (no reference yet),
    // so a malformed extraction - an EMI monthly installment, a
    // pre-order deposit, a decimal slip - would have sailed straight
    // into phone_prices completely unfiltered on initial import.
    $source = PhoneSource::factory()->create(['reliability_score' => 90]);
    $runner = app(PhoneImportRunner::class);

    $raw = makePhoneRaw('Galaxy Deposit Glitch');
    $raw['variants'][0]['price']['official_bd'] = '500'; // e.g. a booking deposit, not the phone's price

    $runner->run(new FakeArrayPhoneSource([$raw]), $source, ImportRunTypeEnum::MANUAL);

    $phone = Phone::query()->where('slug', 'samsung-galaxy-deposit-glitch')->firstOrFail();
    $variant = $phone->variants()->firstOrFail();

    expect($variant->marketPrices()->where('price_type', 'official_bd')->exists())->toBeFalse()
        ->and($variant->prices()->where('price_type', 'official_bd')->exists())->toBeFalse();

    $review = PhoneDataReview::query()->where('phone_variant_id', $variant->id)->where('reason', 'price_outlier')->firstOrFail();
    expect((float) $review->details['submitted_amount'])->toBe(500.0);
});

it('never lets a wildly implausible new price silently overwrite an existing trustworthy one - it goes to review instead', function () {
    $source = PhoneSource::factory()->create(['reliability_score' => 90]);
    $runner = app(PhoneImportRunner::class);

    $runner->run(new FakeArrayPhoneSource([makePhoneRaw('Galaxy Trustworthy Price')]), $source, ImportRunTypeEnum::MANUAL);

    $phone = Phone::query()->where('slug', 'samsung-galaxy-trustworthy-price')->firstOrFail();
    $variant = $phone->variants()->firstOrFail();
    expect((float) $variant->marketPrices()->where('price_type', 'official_bd')->firstOrFail()->price)->toBe(29999.0);

    // A malformed re-scrape reports a price an order of magnitude off.
    $malformed = makePhoneRaw('Galaxy Trustworthy Price');
    $malformed['variants'][0]['price']['official_bd'] = '2,999';
    $runner->run(new FakeArrayPhoneSource([$malformed]), $source, ImportRunTypeEnum::NIGHTLY);

    $variant->refresh();
    expect((float) $variant->marketPrices()->where('price_type', 'official_bd')->firstOrFail()->price)->toBe(29999.0)
        ->and($variant->prices()->where('price_type', 'official_bd')->firstOrFail()->amount)->toEqual('29999.00');

    $review = PhoneDataReview::query()->where('phone_variant_id', $variant->id)->where('reason', 'price_outlier')->firstOrFail();
    expect((float) $review->details['submitted_amount'])->toBe(2999.0)
        ->and((float) $review->details['reference_price'])->toBe(29999.0);
});
