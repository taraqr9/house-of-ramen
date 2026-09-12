<?php

namespace App\Services\PhoneImport\Sources\Retailers;

use App\Enums\PriceTypeEnum;
use App\Enums\RetailerMatchStatusEnum;
use App\Models\Phone;
use App\Models\PhoneRetailerMatchAttempt;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\Normalization\SpecNormalizer;
use App\Services\PhoneImport\Sources\BangladeshRetailerSource;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Base for a real Bangladesh retailer's PRICE feed, driven by generic
 * catalogue-wide DISCOVERY rather than a manually curated URL list.
 *
 * For every active phone, this builds a small set of candidate product
 * URLs from the phone's own brand/model (the same slug conventions
 * SpecNormalizer already uses), fetches them, and treats a genuinely
 * parseable product page whose declared name matches the expected
 * phone as a match - never a guess. HTTP status codes are deliberately
 * NOT trusted as a match/no-match signal: verified 2026-08-26 that
 * Dazzle returns 200 with a generic shell for a completely nonexistent
 * product slug, and Sumash Tech returns 500 (not 404) for a missing
 * one - the only reliable signal on either site is whether the fetched
 * page actually contains a parseable Product record (see
 * parseListingPage() and nameLooksLikeExpectedPhone()).
 *
 * A marketplace retailer's official-channel listing and its grey-import
 * listing (see supportsOfficialSuffix()) are discovered as two
 * independent "lanes" - one PhoneRetailerMatchAttempt row each,
 * PriceTypeEnum-keyed - since a URL guess for one can succeed while the
 * other fails, and each needs its own retry/recheck schedule.
 * PhoneRetailerMatchAttempt is what makes this scalable and resumable
 * across ~500 phones: once a phone/retailer/price-type lane has a
 * definitive outcome, it isn't re-probed with every candidate URL on
 * every run (see RetailerMatchStatusEnum::recheckAfterDays()) - a
 * MATCHED lane goes straight back to its already-known URL for a price
 * refresh, a NO_CANDIDATE lane is left alone for a week, and so on.
 */
abstract class RetailerListingSource extends BangladeshRetailerSource
{
    /** Display name written to phone_prices.store. */
    abstract protected function retailerName(): string;

    /** Must match this row's key in config/phone_sources.php. */
    abstract protected function retailerKey(): string;

    /**
     * Parse one already-fetched product page for this retailer.
     *
     * @return array{amount: float, availability: ?string, name: ?string}|null null when no
     *                                                                         reliable price could be extracted - the candidate is treated as not-found, never guessed.
     */
    abstract protected function parseListingPage(string $html): ?array;

    abstract protected function productUrl(string $slug): string;

    /**
     * The price_type a plain (non-suffixed) product URL represents for
     * this retailer. Star Tech is an authorized retailer with one
     * listing per phone (official_bd); the marketplace retailers'
     * default/undecorated listing is their grey-import stock
     * (unofficial_bd) - see config('phone_pricing.known_retailer_types').
     */
    abstract protected function defaultPriceType(): PriceTypeEnum;

    /**
     * Whether this retailer also publishes a separate, distinctly-named
     * "-official" listing for the authorized-channel price of the same
     * phone (verified true for Dazzle, Sumash Tech, and Rio
     * International's URL conventions on 2026-08-26). False by default.
     */
    protected function supportsOfficialSuffix(): bool
    {
        return false;
    }

    protected function officialSuffix(): string
    {
        return '-official';
    }

    /** Max concurrent HTTP requests in flight for one fetchBatch() call. */
    protected function poolConcurrency(): int
    {
        return 8;
    }

    /**
     * @return list<PriceTypeEnum> every price_type this retailer is
     *                             independently discoverable under.
     */
    protected function priceTypesToDiscover(): array
    {
        $types = [$this->defaultPriceType()];

        if ($this->supportsOfficialSuffix() && $this->defaultPriceType() !== PriceTypeEnum::OFFICIAL_BD) {
            $types[] = PriceTypeEnum::OFFICIAL_BD;
        }

        return $types;
    }

    public function fetchBatch(?array $cursor, int $limit): array
    {
        $lastId = $cursor['last_phone_id'] ?? 0;

        $phones = Phone::query()
            ->where('is_active', true)
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit($limit)
            ->with(['variants', 'brand'])
            ->get();

        if ($phones->isEmpty()) {
            return ['items' => [], 'cursor' => ['last_phone_id' => $lastId], 'done' => true];
        }

        $existingAttempts = PhoneRetailerMatchAttempt::query()
            ->where('source_key', $this->retailerKey())
            ->whereIn('phone_id', $phones->pluck('id'))
            ->get()
            ->groupBy('phone_id');

        // One "plan" per (phone, price-type lane) that's due for a check
        // this run, each with its own candidate URL(s) - a MATCHED lane
        // reuses its already-known URL (1 request); everything else gets
        // the full slug-guessing probe. Every request across every
        // phone/lane in this chunk is gathered into ONE pool below so
        // discovery scales across hundreds of phones instead of paying
        // one-request-at-a-time latency.
        $plans = [];
        $requests = [];

        foreach ($phones as $phone) {
            $attemptsByType = ($existingAttempts->get($phone->id) ?? collect())->keyBy(fn ($a) => $a->price_type->value);

            foreach ($this->priceTypesToDiscover() as $priceType) {
                $attempt = $attemptsByType->get($priceType->value);

                if ($attempt && $attempt->next_check_at->isFuture()) {
                    continue;
                }

                $candidates = ($attempt && $attempt->status === RetailerMatchStatusEnum::MATCHED && $attempt->candidate_url)
                    ? [['url' => $attempt->candidate_url]]
                    : $this->buildCandidateUrls($phone, $priceType);

                $planKey = "{$phone->id}:{$priceType->value}";
                $plans[$planKey] = compact('phone', 'priceType', 'candidates', 'attempt');

                foreach ($candidates as $index => $candidate) {
                    $requests["{$planKey}:{$index}"] = $candidate['url'];
                }
            }
        }

        $responses = $this->fetchPool($requests);

        $observationsByPhone = [];

        foreach ($plans as $planKey => $plan) {
            $result = $this->evaluateLane($plan['phone'], $plan['candidates'], $responses, $planKey);
            $this->recordAttempt($plan['phone'], $plan['priceType'], $result, $plan['attempt']);

            if ($result['status'] === RetailerMatchStatusEnum::MATCHED) {
                $observationsByPhone[$plan['phone']->id]['phone'] = $plan['phone'];
                $observationsByPhone[$plan['phone']->id]['observations'][] = $result + ['price_type' => $plan['priceType']];
            }
        }

        $items = collect($observationsByPhone)
            ->map(fn (array $entry) => $this->buildRawRecord($entry['phone'], $entry['observations']))
            ->values()
            ->all();

        $newLastId = $phones->last()->id;

        return [
            'items' => $items,
            'cursor' => ['last_phone_id' => $newLastId],
            'done' => $phones->count() < $limit,
        ];
    }

    /**
     * @return list<array{url: string}>
     */
    protected function buildCandidateUrls(Phone $phone, PriceTypeEnum $priceType): array
    {
        $brand = $phone->brand->name;
        $model = $phone->name;

        // phoneSlug() is the brand-duplicate-aware form (same one this
        // phone's own slug column uses) - the primary candidate. Many
        // catalogue phone names already include the brand ("Xiaomi 14T",
        // "Huawei Nova 14"), so naively concatenating brand+model here
        // produced literal "xiaomi-xiaomi-14t"-style URLs that no real
        // retailer ever serves - a real bug that made most of the
        // catalogue's "Brand Model" naming pattern unmatchable. The
        // second candidate is deliberately the OPPOSITE form as a
        // fallback: a retailer that drops a well-known sub-brand
        // (verified for Rio + Apple: "iphone-14-pro", not
        // "apple-iphone-14-pro") needs the bare-model slug; one that
        // redundantly re-states an already-included brand needs the
        // fully-concatenated form.
        $primarySlug = SpecNormalizer::phoneSlug($brand, $model);
        $fallbackSlug = str_starts_with(Str::lower($model), Str::lower($brand))
            ? SpecNormalizer::slug($brand, $model)
            : SpecNormalizer::slug($model);

        $baseSlugs = array_unique(array_filter([
            $primarySlug,
            $fallbackSlug,
            ...$this->perVariantSlugs($phone),
        ]));

        // A retailer-specific SEO-slug suffix pattern (see e.g. Dazzle's
        // "-price-in-bangladesh" - verified 2026-08-26 on ~11% of its
        // real product sitemap) tried as extra candidates alongside the
        // bare slug, never instead of it.
        $slugs = array_unique(collect($baseSlugs)
            ->flatMap(fn ($slug) => [$slug, ...collect($this->additionalSuffixes())->map(fn ($suffix) => $slug.$suffix)])
            ->all());

        $useSuffix = $priceType === PriceTypeEnum::OFFICIAL_BD && $priceType !== $this->defaultPriceType();

        return collect($slugs)
            ->map(fn ($slug) => ['url' => $this->productUrl($useSuffix ? $slug.$this->officialSuffix() : $slug)])
            ->all();
    }

    /**
     * Extra SEO-slug suffixes this retailer is verified to use on at
     * least some real product URLs, beyond the bare brand/model slug -
     * e.g. Dazzle's "-price-in-bangladesh". Empty by default; only
     * override with a suffix actually observed on that retailer's own
     * sitemap, never guessed generically for every retailer.
     *
     * @return list<string>
     */
    protected function additionalSuffixes(): array
    {
        return [];
    }

    /**
     * Extra, per-variant candidate slugs for a retailer whose URL
     * convention is known to encode RAM/storage directly in the product
     * slug for at least some of a phone's variants - e.g. Star Tech
     * (verified 2026-08-26: the Galaxy S24 Ultra's 12/256GB is
     * "samsung-galaxy-s24-ultra" while its 12/512GB is a SEPARATE
     * "samsung-galaxy-s24-ultra-12-512gb"). Generic and phone-agnostic -
     * applies to any multi-variant phone on a retailer that follows this
     * convention, never guessed for a retailer whose convention isn't
     * actually verified (returns none by default). Each candidate still
     * goes through the same parse + name-match + variant-attribution
     * pipeline as every other candidate - this only widens what gets
     * tried, it doesn't bypass any safety check.
     *
     * @return list<string>
     */
    protected function perVariantSlugs(Phone $phone): array
    {
        return [];
    }

    /**
     * @param  array<string, string>  $requests  ["{planKey}:{index}" => url]
     * @return array<string, string|null> body per key, or null on failure
     */
    protected function fetchPool(array $requests): array
    {
        if (empty($requests)) {
            return [];
        }

        $results = [];
        $chunks = array_chunk($requests, $this->poolConcurrency(), true);
        $chunkCount = count($chunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            $startedAt = microtime(true);

            $responses = Http::pool(function (Pool $pool) use ($chunk) {
                $pooled = [];

                foreach ($chunk as $key => $url) {
                    $pooled[] = $pool->as($key)
                        ->withHeaders(['User-Agent' => $this->userAgent()])
                        ->connectTimeout(10)
                        ->timeout(15)
                        // 2 total attempts (1 real retry) - retry(1, ...) is a no-op:
                        // PendingRequest::getMaximumAttempts() treats a scalar $times
                        // as the TOTAL attempt count, not "number of retries", so
                        // retry(1, ...) never actually retries anything. throw:false
                        // keeps a non-2xx final response as an inspectable Response
                        // rather than converting it to a thrown/rejected exception -
                        // see class docblock: HTTP status is never trusted as the
                        // match/no-match signal, only whether the body parses.
                        ->retry(2, 300, throw: false)
                        ->get($url);
                }

                return $pooled;
            });

            $elapsed = round(microtime(true) - $startedAt, 2);

            // A chunk is bounded to ~15s per request by the timeout above, but a
            // whole run can still look "hung" from the outside across many chunks -
            // this makes a genuinely slow/unresponsive source (vs. a real infinite
            // loop) visible in the logs instead of indistinguishable silence. Uses
            // the default channel, not 'custom_error' (level=error, see
            // bootstrap/app.php - reserved for the global exception handler and
            // would silently drop an info-level entry).
            Log::info('Retailer discovery chunk fetched', [
                'source' => $this->sourceKey,
                'chunk' => $chunkIndex + 1,
                'of' => $chunkCount,
                'requests' => count($chunk),
                'seconds' => $elapsed,
            ]);

            foreach ($chunk as $key => $url) {
                $response = $responses[$key] ?? null;

                if ($response instanceof \Throwable || $response === null) {
                    // Same channel note as above - 'custom_error' filters below
                    // error level and would swallow this warning entirely.
                    Log::warning('Retailer discovery fetch failed', [
                        'source' => $this->sourceKey, 'url' => $url,
                        'error' => $response instanceof \Throwable ? $response->getMessage() : 'no response',
                    ]);
                    $results[$key] = null;

                    continue;
                }

                // Parsed for content regardless of status - see class docblock.
                $results[$key] = $response->body();
            }
        }

        return $results;
    }

    /**
     * @param  list<array{url: string}>  $candidates
     * @param  array<string, string|null>  $responses
     * @return array{status: RetailerMatchStatusEnum, url: ?string, amount: ?float, availability: ?string, variant: ?PhoneVariant, http_status: ?int, note: ?string}
     */
    protected function evaluateLane(Phone $phone, array $candidates, array $responses, string $planKey): array
    {
        $anyFetchSucceeded = false;
        $bestInconclusive = null; // the most informative non-match seen, for diagnostics if nothing ever matches

        foreach ($candidates as $index => $candidate) {
            $html = $responses["{$planKey}:{$index}"] ?? null;

            if ($html === null) {
                continue;
            }

            $anyFetchSucceeded = true;
            $parsed = $this->parseListingPage($html);

            if ($parsed === null) {
                continue;
            }

            if (! $this->nameLooksLikeExpectedPhone($parsed['name'] ?? null, $phone->name)) {
                // Not a match, but not fatal either - a later candidate
                // (e.g. a per-variant slug, see perVariantSlugs()) may
                // still resolve to the right phone/variant. Kept only as
                // a fallback if nothing better turns up.
                $bestInconclusive ??= [
                    'status' => RetailerMatchStatusEnum::NAME_MISMATCH, 'url' => $candidate['url'],
                    'amount' => null, 'availability' => null, 'variant' => null,
                    'http_status' => null, 'note' => "declared name: {$parsed['name']}",
                ];

                continue;
            }

            $variantInfo = SpecNormalizer::extractVariantFromName($parsed['name'] ?? '');
            $variant = $this->attributeVariant($phone, $variantInfo['ram_gb'], $variantInfo['storage_gb']);

            if (! $variant) {
                // Ambiguous for THIS candidate (e.g. the base slug, which
                // several variants could plausibly share) - a more
                // specific candidate later in the list may still resolve
                // cleanly, so keep trying rather than give up here.
                $bestInconclusive ??= [
                    'status' => RetailerMatchStatusEnum::VARIANT_AMBIGUOUS, 'url' => $candidate['url'],
                    'amount' => null, 'availability' => null, 'variant' => null,
                    'http_status' => null, 'note' => 'extracted ram='.($variantInfo['ram_gb'] ?? '?').' storage='.($variantInfo['storage_gb'] ?? '?'),
                ];

                continue;
            }

            return [
                'status' => RetailerMatchStatusEnum::MATCHED, 'url' => $candidate['url'],
                'amount' => $parsed['amount'], 'availability' => $parsed['availability'], 'variant' => $variant,
                'http_status' => null, 'note' => null,
            ];
        }

        if ($bestInconclusive !== null) {
            return $bestInconclusive;
        }

        return [
            'status' => $anyFetchSucceeded ? RetailerMatchStatusEnum::NO_CANDIDATE : RetailerMatchStatusEnum::FETCH_FAILED,
            'url' => null, 'amount' => null, 'availability' => null, 'variant' => null,
            'http_status' => null, 'note' => null,
        ];
    }

    /**
     * Attributes a discovered product's (ram, storage) to exactly one of
     * the phone's active variants, or returns null when it can't be done
     * with confidence - the phone having only one active variant makes
     * name-extraction unnecessary; multiple variants require an exact,
     * unambiguous match. Never guesses among several candidates.
     */
    protected function attributeVariant(Phone $phone, ?int $ramGb, ?int $storageGb): ?PhoneVariant
    {
        $variants = $phone->variants->where('is_active', true);

        if ($variants->count() === 1) {
            return $variants->first();
        }

        if ($storageGb === null) {
            return null;
        }

        $candidates = $variants->filter(fn (PhoneVariant $v) => $v->storage_gb === $storageGb
            && ($ramGb === null || $v->ram_gb === $ramGb));

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    protected function recordAttempt(Phone $phone, PriceTypeEnum $priceType, array $result, ?PhoneRetailerMatchAttempt $existing): void
    {
        $status = $result['status'];

        PhoneRetailerMatchAttempt::query()->updateOrCreate(
            ['phone_id' => $phone->id, 'source_key' => $this->retailerKey(), 'price_type' => $priceType->value],
            [
                'status' => $status->value,
                'candidate_url' => $result['url'],
                'matched_variant_id' => $result['variant']?->id,
                'http_status' => $result['http_status'],
                'attempts' => ($existing?->attempts ?? 0) + 1,
                'note' => $result['note'],
                'checked_at' => now(),
                'next_check_at' => now()->addDays($status->recheckAfterDays()),
            ]
        );
    }

    /**
     * @param  list<array{status: RetailerMatchStatusEnum, url: string, amount: float, availability: ?string, variant: PhoneVariant, price_type: PriceTypeEnum}>  $observations
     * @return array<string, mixed> one raw phone record, shaped exactly
     *                              like PhoneImportRunner::normalize() expects - possibly with
     *                              several variant entries if different price-type lanes matched
     *                              different variants.
     */
    protected function buildRawRecord(Phone $phone, array $observations): array
    {
        $variantsByKey = [];

        foreach ($observations as $obs) {
            /** @var PhoneVariant $variant */
            $variant = $obs['variant'];
            $priceKey = $obs['price_type'] === PriceTypeEnum::OFFICIAL_BD ? 'official_bd' : 'unofficial_bd';

            $variantsByKey[$variant->id] ??= [
                'ram' => $variant->ram_gb ? "{$variant->ram_gb} GB" : null,
                'storage' => $variant->storage_gb ? "{$variant->storage_gb} GB" : null,
                'region' => $variant->region,
                'is_official_bd' => $variant->is_official_bd,
                'store' => $this->retailerName(),
                'price' => [],
            ];

            $variantsByKey[$variant->id]['price'][$priceKey] = [
                'amount' => $obs['amount'],
                'store' => $this->retailerName(),
                'source_url' => $obs['url'],
                'availability' => $obs['availability'],
            ];
            $variantsByKey[$variant->id]['availability'] = $obs['availability'];
        }

        return [
            'brand' => $phone->brand->name,
            'model' => $phone->name,
            'external_ref' => "{$this->sourceKey}-{$phone->slug}",
            'variants' => array_values($variantsByKey),
        ];
    }

    /**
     * Cheap guard against a URL guess coincidentally landing on an
     * unrelated real product: the retailer's own declared product name
     * must at least contain the expected MODEL's significant words.
     *
     * Brand is deliberately NOT required to appear (verified 2026-08-26:
     * real retailer listings routinely drop it for a recognizable
     * sub-brand - Sumash Tech titles Google's Pixel 9 just "Pixel 9",
     * with no "Google" anywhere in the name - requiring it produced real
     * false-negative NAME_MISMATCHes). "+" is normalized to " plus" (a
     * retailer spelling "S25+" as "S25 Plus" isn't a different phone). A
     * letter immediately followed by a digit gets a space inserted
     * before tokenizing (verified 2026-08-26: real retailers spell
     * "Fold7"/"Reno14" as "Fold 7"/"Reno 14" - applying this to both
     * sides means the comparison doesn't care which spacing convention
     * either string used). A short numeric token (a model number/
     * generation digit) is kept even though it's under the general
     * 2-character length floor - it's often the ONLY token that
     * actually discriminates one model from its sibling (e.g. "Pixel 9"
     * vs "Pixel 8").
     *
     * Numeric-bearing tokens (containing a digit - "a05", "s24", "14t",
     * "7") are treated as the real discriminator and must ALL appear;
     * purely alphabetic tokens ("galaxy", "series", "edge") are only a
     * bonus signal, not required. Verified 2026-08-26 this is a real,
     * common naming variance, not an edge case: retailers routinely drop
     * a brand's marketing/family word while always keeping the actual
     * model code - Dazzle titles the Galaxy A05 just "Samsung A05
     * Official", with no "Galaxy" anywhere in the name.
     */
    protected function nameLooksLikeExpectedPhone(?string $foundName, string $model): bool
    {
        if ($foundName === null || $foundName === '') {
            return true;
        }

        $normalize = function (string $value): string {
            $value = str_replace('+', ' plus', $value);
            $value = preg_replace('/([a-zA-Z])(\d)/', '$1 $2', $value);
            // Strip stray punctuation - verified 2026-08-26: the
            // catalogue names one phone "Nothing Phone (2)" while real
            // retailers just say "Nothing Phone 2" - the parentheses
            // alone broke an otherwise-exact numeric-token match.
            $value = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $value);

            return Str::lower($value);
        };

        $found = $normalize($foundName);
        $words = collect(preg_split('/\s+/', trim($normalize($model))))
            ->filter(fn ($word) => strlen($word) >= 2 || ctype_digit($word));

        if ($words->isEmpty()) {
            return true;
        }

        [$numeric, $alpha] = $words->partition(fn ($word) => preg_match('/\d/', $word));

        if ($numeric->isNotEmpty()) {
            return $numeric->every(fn ($word) => str_contains($found, $word));
        }

        $matches = $alpha->filter(fn ($word) => str_contains($found, $word))->count();

        return ($matches / $alpha->count()) >= 0.6;
    }

    protected function userAgent(): string
    {
        return $this->config['user_agent'] ?? 'PhoneKinboCatalogueBot/1.0 (contact: developer@bol-online.com)';
    }

    /**
     * Every schema.org Offer.availability URL this pipeline needs to
     * recognize, mapped to the vocabulary PhoneAvailability already
     * stores (see PhoneImportRunner::upsertAvailability()).
     */
    protected function mapSchemaAvailability(?string $schemaUrl): ?string
    {
        if ($schemaUrl === null) {
            return null;
        }

        return match (true) {
            str_contains($schemaUrl, 'OutOfStock') => 'out_of_stock',
            str_contains($schemaUrl, 'InStock') => 'in_stock',
            str_contains($schemaUrl, 'PreOrder') => 'preorder',
            str_contains($schemaUrl, 'Discontinued') => 'discontinued',
            default => null,
        };
    }
}
