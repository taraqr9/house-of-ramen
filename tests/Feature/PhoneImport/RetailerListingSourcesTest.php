<?php

use App\Enums\ImportRunTypeEnum;
use App\Enums\PriceTypeEnum;
use App\Enums\RetailerMatchStatusEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneRetailerMatchAttempt;
use App\Models\PhoneSource;
use App\Services\PhoneImport\Normalization\SpecNormalizer;
use App\Services\PhoneImport\PhoneImportRunner;
use App\Services\PhoneImport\Sources\Retailers\AppleGadgetsSource;
use App\Services\PhoneImport\Sources\Retailers\DazzleSource;
use App\Services\PhoneImport\Sources\Retailers\RioInternationalSource;
use App\Services\PhoneImport\Sources\Retailers\StarTechSource;
use App\Services\PhoneImport\Sources\Retailers\SumashTechSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function retailerFixture(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/retailers/{$name}"));
}

function catalogPhone(string $brandName, string $model, array $variants = [[]]): Phone
{
    $brand = Brand::query()->firstOrCreate(['slug' => Str::slug($brandName)], ['name' => $brandName, 'is_active' => true]);
    $phone = Phone::factory()->for($brand, 'brand')->create([
        'name' => $model,
        'slug' => SpecNormalizer::phoneSlug($brandName, $model),
    ]);

    foreach ($variants as $i => $overrides) {
        $phone->variants()->create(array_merge([
            'slug' => $phone->slug.'-'.$i,
            'ram_gb' => 12,
            'storage_gb' => 256,
            'region' => 'Global',
            'is_official_bd' => true,
            'status' => 'available',
            'is_active' => true,
        ], $overrides));
    }

    return $phone->fresh(['variants', 'brand']);
}

function importSourceFor(string $key): PhoneSource
{
    return PhoneSource::create([
        'key' => $key, 'name' => $key, 'type' => 'bd_retailer',
        'reliability_score' => 80, 'requires_review' => false, 'is_active' => true,
    ]);
}

// -----------------------------------------------------------------------
// Star Tech - microdata, single-variant discovery
// -----------------------------------------------------------------------

it('discovers a real Star Tech listing by guessing the phone-slug URL and writes an observation end-to-end', function () {
    $phone = catalogPhone('Samsung', 'Galaxy S24 Ultra');
    Http::fake(['www.startech.com.bd/samsung-galaxy-s24-ultra' => Http::response(retailerFixture('startech_s24_ultra.html'), 200)]);

    $source = new StarTechSource('star_tech', []);
    $sourceRow = importSourceFor('star_tech');

    app(PhoneImportRunner::class)->run($source, $sourceRow, ImportRunTypeEnum::MANUAL);

    $variant = $phone->fresh()->variants()->first();
    $price = $variant->prices()->where('price_type', 'official_bd')->whereHas('store', fn ($q) => $q->where('name', 'Star Tech'))->first();

    expect($price)->not->toBeNull()
        ->and((float) $price->amount)->toBe(243999.0);

    $attempt = PhoneRetailerMatchAttempt::where('phone_id', $phone->id)->where('source_key', 'star_tech')->first();
    expect($attempt->status)->toBe(RetailerMatchStatusEnum::MATCHED)
        ->and($attempt->candidate_url)->toBe('https://www.startech.com.bd/samsung-galaxy-s24-ultra')
        ->and($attempt->matched_variant_id)->toBe($variant->id);
});

it('records NO_CANDIDATE and writes nothing when no candidate URL resolves to a real product', function () {
    catalogPhone('Acme', 'Widget Phone 9000');
    Http::fake(['*' => Http::response('<html><title>Star Tech</title></html>', 200)]);

    $source = new StarTechSource('star_tech', []);
    $batch = $source->fetchBatch(null, 20);

    expect($batch['items'])->toBeEmpty();

    $attempt = PhoneRetailerMatchAttempt::first();
    expect($attempt->status)->toBe(RetailerMatchStatusEnum::NO_CANDIDATE);
});

it('records FETCH_FAILED (not NO_CANDIDATE) when every candidate request errors, and schedules a quick retry', function () {
    catalogPhone('Acme', 'Widget Phone 9000');
    Http::fake(['*' => fn () => throw new ConnectionException('timed out')]);

    $source = new StarTechSource('star_tech', []);
    $source->fetchBatch(null, 20);

    $attempt = PhoneRetailerMatchAttempt::first();
    expect($attempt->status)->toBe(RetailerMatchStatusEnum::FETCH_FAILED)
        ->and($attempt->next_check_at->diffInDays(now(), true))->toBeLessThan(2);
});

it('logs a failed candidate fetch via Log::warning on the default channel, not the error-level custom_error channel', function () {
    catalogPhone('Acme', 'Widget Phone 9000');
    Http::fake(['*' => fn () => throw new ConnectionException('timed out')]);

    Log::spy();

    $source = new StarTechSource('star_tech', []);
    $source->fetchBatch(null, 20);

    // custom_error is filtered to error-level and above by bootstrap/app.php -
    // an info/warning entry logged there would be silently dropped.
    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context) => $message === 'Retailer discovery fetch failed' && $context['source'] === 'star_tech'
    );
});

it('logs each fetch chunk with its size and elapsed time, so a slow catalogue-wide pass is visible in the logs', function () {
    $phone = catalogPhone('Samsung', 'Galaxy S24 Ultra');
    Http::fake(['www.startech.com.bd/samsung-galaxy-s24-ultra' => Http::response(retailerFixture('startech_s24_ultra.html'), 200)]);

    Log::spy();

    $source = new StarTechSource('star_tech', []);
    $sourceRow = importSourceFor('star_tech');
    app(PhoneImportRunner::class)->run($source, $sourceRow, ImportRunTypeEnum::MANUAL);

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context) => $message === 'Retailer discovery chunk fetched'
            && $context['source'] === 'star_tech'
            && $context['chunk'] === 1
            && isset($context['seconds'])
    );
});

it('does not re-probe a phone whose match attempt is not yet due for recheck (idempotent skip)', function () {
    $phone = catalogPhone('Samsung', 'Galaxy S24 Ultra');
    PhoneRetailerMatchAttempt::create([
        'phone_id' => $phone->id, 'source_key' => 'star_tech', 'price_type' => 'official_bd',
        'status' => RetailerMatchStatusEnum::NO_CANDIDATE->value,
        'checked_at' => now(), 'next_check_at' => now()->addDays(5),
    ]);

    Http::fake(['*' => Http::response('should never be hit', 200)]);

    $source = new StarTechSource('star_tech', []);
    $batch = $source->fetchBatch(null, 20);

    expect($batch['items'])->toBeEmpty();
    Http::assertNothingSent();
});

it('a MATCHED phone re-fetches only its already-known URL instead of re-probing every candidate', function () {
    $phone = catalogPhone('Samsung', 'Galaxy S24 Ultra');
    $variant = $phone->variants->first();
    PhoneRetailerMatchAttempt::create([
        'phone_id' => $phone->id, 'source_key' => 'star_tech', 'status' => RetailerMatchStatusEnum::MATCHED->value,
        'candidate_url' => 'https://www.startech.com.bd/samsung-galaxy-s24-ultra', 'matched_variant_id' => $variant->id,
        'price_type' => 'official_bd', 'checked_at' => now()->subDays(2), 'next_check_at' => now()->subHour(),
    ]);

    Http::fake(['www.startech.com.bd/samsung-galaxy-s24-ultra' => Http::response(retailerFixture('startech_s24_ultra.html'), 200)]);

    $source = new StarTechSource('star_tech', []);
    $batch = $source->fetchBatch(null, 20);

    expect($batch['items'])->toHaveCount(1);
    Http::assertSentCount(1); // not multiple candidate slugs
});

// -----------------------------------------------------------------------
// Variant attribution - the "never guess" requirement
// -----------------------------------------------------------------------

it('attributes a discovered price to the correct variant when the phone has several and the page name discloses RAM/storage', function () {
    $phone = catalogPhone('Samsung', 'Galaxy S24 Ultra', [
        ['ram_gb' => 12, 'storage_gb' => 256],
        ['ram_gb' => 12, 'storage_gb' => 512],
    ]);
    $target512 = $phone->variants->firstWhere('storage_gb', 512);

    // Reuse the real 512GB fixture but give it a non-zero price for this
    // test - only its declared name "(12/512GB)" matters here.
    $html = str_replace('content="0.0000"', 'content="199999.0000"', retailerFixture('startech_s24_ultra_512_unavailable.html'));
    Http::fake(['www.startech.com.bd/samsung-galaxy-s24-ultra' => Http::response($html, 200)]);

    $source = new StarTechSource('star_tech', []);
    $source->fetchBatch(null, 20);

    $attempt = PhoneRetailerMatchAttempt::where('phone_id', $phone->id)->first();
    expect($attempt->status)->toBe(RetailerMatchStatusEnum::MATCHED)
        ->and($attempt->matched_variant_id)->toBe($target512->id);
});

it('records VARIANT_AMBIGUOUS and writes nothing when the phone has multiple variants and the page name does not disclose which one', function () {
    $phone = catalogPhone('Samsung', 'Galaxy S24 Ultra', [
        ['ram_gb' => 12, 'storage_gb' => 256],
        ['ram_gb' => 12, 'storage_gb' => 512],
    ]);

    // A JSON-LD Product name with no RAM/storage tokens at all.
    $html = '<html><head><script type="application/ld+json">{"@type":"Product","name":"Samsung Galaxy S24 Ultra","offers":{"@type":"Offer","price":150000,"priceCurrency":"BDT","availability":"https://schema.org/InStock"}}</script></head></html>';
    Http::fake(['dazzle.com.bd/*' => Http::response($html, 200)]);

    $source = new DazzleSource('dazzle', []);
    $batch = $source->fetchBatch(null, 20);

    expect($batch['items'])->toBeEmpty();
    $attempt = PhoneRetailerMatchAttempt::where('phone_id', $phone->id)->first();
    expect($attempt->status)->toBe(RetailerMatchStatusEnum::VARIANT_AMBIGUOUS);
});

it('needs no name-disclosed variant info when the phone has only one active variant - unambiguous by construction', function () {
    $phone = catalogPhone('Samsung', 'Galaxy S24 Ultra'); // single variant
    $variant = $phone->variants->first();

    $html = '<html><head><script type="application/ld+json">{"@type":"Product","name":"Samsung Galaxy S24 Ultra","offers":{"@type":"Offer","price":150000,"priceCurrency":"BDT","availability":"https://schema.org/InStock"}}</script></head></html>';
    Http::fake(['dazzle.com.bd/*' => Http::response($html, 200)]);

    $source = new DazzleSource('dazzle', []);
    $source->fetchBatch(null, 20);

    $attempt = PhoneRetailerMatchAttempt::where('phone_id', $phone->id)->first();
    expect($attempt->status)->toBe(RetailerMatchStatusEnum::MATCHED)
        ->and($attempt->matched_variant_id)->toBe($variant->id);
});

// -----------------------------------------------------------------------
// The status-code-is-unreliable quirks (Dazzle always-200, Sumash 500-for-missing)
// -----------------------------------------------------------------------

it('treats a Dazzle 200 response with no Product JSON-LD as no match, not a false positive', function () {
    catalogPhone('Acme', 'Widget Phone 9000');
    Http::fake(['dazzle.com.bd/*' => Http::response('<html><body>generic shell, no product data</body></html>', 200)]);

    $source = new DazzleSource('dazzle', []);
    $batch = $source->fetchBatch(null, 20);

    expect($batch['items'])->toBeEmpty();
    expect(PhoneRetailerMatchAttempt::first()->status)->toBe(RetailerMatchStatusEnum::NO_CANDIDATE);
});

it('treats a Sumash Tech 500 response the same as a normal miss, not a fetch failure', function () {
    catalogPhone('Acme', 'Widget Phone 9000');
    Http::fake(['www.sumashtech.com/*' => Http::response('Internal Server Error', 500)]);

    $source = new SumashTechSource('sumash_tech', []);
    $batch = $source->fetchBatch(null, 20);

    expect($batch['items'])->toBeEmpty();
    expect(PhoneRetailerMatchAttempt::first()->status)->toBe(RetailerMatchStatusEnum::NO_CANDIDATE);
});

// -----------------------------------------------------------------------
// Official-suffix discovery (Dazzle/Sumash/Rio real convention)
// -----------------------------------------------------------------------

it('discovers a separate official-channel listing via the -official URL suffix for marketplace retailers', function () {
    $phone = catalogPhone('Samsung', 'Galaxy S24 Ultra');
    Http::fake([
        'dazzle.com.bd/product/samsung-galaxy-s24-ultra-official' => Http::response(retailerFixture('dazzle_s24_ultra_official.html'), 200),
        'dazzle.com.bd/product/samsung-galaxy-s24-ultra' => Http::response(retailerFixture('dazzle_s24_ultra_unofficial.html'), 200),
    ]);

    $source = new DazzleSource('dazzle', []);
    app(PhoneImportRunner::class)->run($source, importSourceFor('dazzle'), ImportRunTypeEnum::MANUAL);

    $variant = $phone->fresh()->variants->first();
    $unofficial = $variant->prices()->where('price_type', 'unofficial_bd')->first();
    $official = $variant->prices()->where('price_type', 'official_bd')->first();

    expect((float) $unofficial->amount)->toBe(88990.0)
        ->and((float) $official->amount)->toBe(243990.0);
});

// -----------------------------------------------------------------------
// Cross-cutting: discovery only ever touches phones already in the catalogue
// -----------------------------------------------------------------------

it('does not duplicate the brand name in candidate URLs when the phone name already includes it - regression for the "xiaomi-xiaomi-14t" bug', function () {
    $phone = catalogPhone('Xiaomi', 'Xiaomi 14T');

    $source = new StarTechSource('star_tech', []);
    $method = new ReflectionMethod($source, 'buildCandidateUrls');
    $urls = collect($method->invoke($source, $phone, PriceTypeEnum::OFFICIAL_BD))->pluck('url');

    // The correct, brand-duplicate-free slug must be the FIRST candidate
    // tried (the duplicated form may still appear later as a fallback,
    // in case some retailer genuinely redundantly restates the brand -
    // but it must never be the only one tried, which was the bug).
    expect($urls->first())->toBe('https://www.startech.com.bd/xiaomi-14t');
});

it('accepts a listing that drops a family word but keeps the exact model code - regression for "Samsung A05 Official" vs "Galaxy A05"', function () {
    $phone = catalogPhone('Samsung', 'Galaxy A05');

    $html = '<html><head><script type="application/ld+json">{"@type":"Product","name":"Samsung A05 Official","offers":{"@type":"Offer","price":15999,"priceCurrency":"BDT","availability":"https://schema.org/InStock"}}</script></head></html>';
    Http::fake(['dazzle.com.bd/*' => Http::response($html, 200)]);

    app(PhoneImportRunner::class)->run(new DazzleSource('dazzle', []), importSourceFor('dazzle'), ImportRunTypeEnum::MANUAL);

    $price = $phone->fresh()->variants->first()->prices()->first();
    expect((float) $price->amount)->toBe(15999.0);
});

it('accepts a listing that spells a generation number with a space when the catalogue name has none - regression for "Fold 7" vs "Fold7"', function () {
    $phone = catalogPhone('Samsung', 'Galaxy Z Fold7');

    $html = '<html><head><script type="application/ld+json">{"@type":"Product","name":"Samsung Galaxy Z Fold 7","offers":{"@type":"Offer","price":189999,"priceCurrency":"BDT","availability":"https://schema.org/InStock"}}</script></head></html>';
    Http::fake(['dazzle.com.bd/*' => Http::response($html, 200)]);

    app(PhoneImportRunner::class)->run(new DazzleSource('dazzle', []), importSourceFor('dazzle'), ImportRunTypeEnum::MANUAL);

    $price = $phone->fresh()->variants->first()->prices()->first();
    expect((float) $price->amount)->toBe(189999.0);
});

it('still rejects a genuinely different model number even after the numeric-token relaxation', function () {
    catalogPhone('Samsung', 'Galaxy A05');

    $html = '<html><head><script type="application/ld+json">{"@type":"Product","name":"Samsung A06 Official","offers":{"@type":"Offer","price":15999,"priceCurrency":"BDT","availability":"https://schema.org/InStock"}}</script></head></html>';
    Http::fake(['dazzle.com.bd/*' => Http::response($html, 200)]);

    $batch = (new DazzleSource('dazzle', []))->fetchBatch(null, 20);

    expect($batch['items'])->toBeEmpty();
    expect(PhoneRetailerMatchAttempt::first()->status)->toBe(RetailerMatchStatusEnum::NAME_MISMATCH);
});

it('rejects a JSON-LD Offer.price of exactly 0 as a delisted placeholder, not a real free price', function () {
    catalogPhone('Samsung', 'Galaxy A05');

    $html = '<html><head><script type="application/ld+json">{"@type":"Product","name":"Samsung Galaxy A05","offers":{"@type":"Offer","price":0,"priceCurrency":"BDT","availability":"https://schema.org/OutOfStock"}}</script></head></html>';
    Http::fake(['dazzle.com.bd/*' => Http::response($html, 200)]);

    $batch = (new DazzleSource('dazzle', []))->fetchBatch(null, 20);

    expect($batch['items'])->toBeEmpty();
});

it('tries Dazzle\'s verified "-price-in-bangladesh" SEO-slug suffix as an extra candidate', function () {
    $phone = catalogPhone('Oppo', 'Oppo Reno15');

    Http::fake([
        'dazzle.com.bd/product/oppo-reno15' => Http::response('<html><body>generic shell, no product data</body></html>', 200),
        'dazzle.com.bd/product/oppo-reno15-price-in-bangladesh' => Http::response(
            '<html><head><script type="application/ld+json">{"@type":"Product","name":"Oppo Reno15","offers":{"@type":"Offer","price":45999,"priceCurrency":"BDT","availability":"https://schema.org/InStock"}}</script></head></html>',
            200
        ),
    ]);

    app(PhoneImportRunner::class)->run(new DazzleSource('dazzle', []), importSourceFor('dazzle'), ImportRunTypeEnum::MANUAL);

    $price = $phone->fresh()->variants->first()->prices()->first();
    expect((float) $price->amount)->toBe(45999.0);
});

it('strips parentheses before matching - regression for "Nothing Phone (2)" vs a retailer\'s plain "Nothing Phone 2"', function () {
    $phone = catalogPhone('Nothing', 'Nothing Phone (2)');

    $html = '<html><head><script type="application/ld+json">{"@type":"Product","name":"Nothing Phone 2 5G","offers":{"@type":"Offer","price":52999,"priceCurrency":"BDT","availability":"https://schema.org/InStock"}}</script></head></html>';
    Http::fake(['dazzle.com.bd/*' => Http::response($html, 200)]);

    app(PhoneImportRunner::class)->run(new DazzleSource('dazzle', []), importSourceFor('dazzle'), ImportRunTypeEnum::MANUAL);

    $price = $phone->fresh()->variants->first()->prices()->first();
    expect((float) $price->amount)->toBe(52999.0);
});

it('never creates a new phone - discovery only iterates phones already in the catalogue', function () {
    expect(Phone::count())->toBe(0);

    Http::fake(['*' => Http::response('irrelevant', 200)]);
    $source = new StarTechSource('star_tech', []);
    $batch = $source->fetchBatch(null, 20);

    expect($batch['items'])->toBeEmpty()
        ->and($batch['done'])->toBeTrue()
        ->and(Phone::count())->toBe(0);
});

it('paginates via a resumable phone-id cursor across multiple batches', function () {
    collect(range(1, 5))->each(fn ($i) => catalogPhone('Acme', "Widget {$i}"));
    Http::fake(['*' => Http::response('<html></html>', 200)]);

    $source = new StarTechSource('star_tech', []);
    $first = $source->fetchBatch(null, 2);
    expect($first['done'])->toBeFalse();

    $second = $source->fetchBatch($first['cursor'], 2);
    expect($second['cursor']['last_phone_id'])->toBeGreaterThan($first['cursor']['last_phone_id']);

    $third = $source->fetchBatch($second['cursor'], 2);
    $fourth = $source->fetchBatch($third['cursor'], 2);
    expect($fourth['done'])->toBeTrue();

    expect(PhoneRetailerMatchAttempt::count())->toBe(5);
});

it('skips an Apple Gadgets listing with no parseable price rather than fabricating one', function () {
    catalogPhone('Samsung', 'Galaxy S24 Ultra');
    Http::fake(['www.applegadgetsbd.com/*' => Http::response(retailerFixture('applegadgets_s24_ultra_tba.html'), 200)]);

    $source = new AppleGadgetsSource('apple_gadgets', []);
    $batch = $source->fetchBatch(null, 20);

    expect($batch['items'])->toBeEmpty();
});

it('never trusts Rio International availability but still records its real price', function () {
    $phone = catalogPhone('Samsung', 'Galaxy S24 Ultra');
    Http::fake([
        'riointernational.com.bd/product/samsung-galaxy-s24-ultra-official' => Http::response(retailerFixture('rio_s24_ultra_official.html'), 200),
        'riointernational.com.bd/product/samsung-galaxy-s24-ultra' => Http::response('<html></html>', 404),
    ]);

    $source = new RioInternationalSource('rio_international', []);
    app(PhoneImportRunner::class)->run($source, importSourceFor('rio_international'), ImportRunTypeEnum::MANUAL);

    $variant = $phone->fresh()->variants->first();
    $price = $variant->prices()->where('price_type', 'official_bd')->first();

    expect((float) $price->amount)->toBe(207999.0);

    $availability = $variant->availabilities()->whereHas('store', fn ($q) => $q->where('name', 'Rio International'))->first();
    expect($availability)->toBeNull(); // deliberately unknown, not asserted
});
