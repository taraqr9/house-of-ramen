<?php

namespace App\Services\PhoneImport;

use App\Enums\ImportRunStatusEnum;
use App\Enums\ImportRunTypeEnum;
use App\Enums\MatchStatusEnum;
use App\Enums\PriceTypeEnum;
use App\Enums\ReviewReasonEnum;
use App\Enums\ReviewStatusEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneAvailability;
use App\Models\PhoneDataConflict;
use App\Models\PhoneDataReview;
use App\Models\PhoneImportRecord;
use App\Models\PhoneImportRun;
use App\Models\PhoneMarketPrice;
use App\Models\PhoneNetworkBand;
use App\Models\PhonePrice;
use App\Models\PhonePriceHistory;
use App\Models\PhoneSource;
use App\Models\PhoneSpec;
use App\Models\PhoneStore;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\Contracts\PhoneSourceProvider;
use App\Services\PhoneImport\Normalization\SpecNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Drives one source provider through the full pipeline - discover,
 * normalize, dedupe, validate, score, and either auto-approve or flag -
 * one batch at a time, persisting the run's cursor and counters after
 * every batch. That per-batch checkpoint is what makes a crash or
 * timeout mid-run safe to resume: re-invoking run() with the same
 * (still PARTIAL/FAILED) PhoneImportRun simply continues from the last
 * saved cursor instead of reprocessing everything from scratch.
 */
class PhoneImportRunner
{
    /** Spec fields important enough to run conflict detection on when sources disagree. */
    protected const CONFLICT_WATCHED_SPEC_FIELDS = [
        'battery_capacity_mah',
        'charging_speed_w',
        'display_size',
        'main_camera',
        'processor',
    ];

    protected const IDENTITY_FIELDS = ['brand', 'model', 'announced_date', 'release_date', 'status'];

    /**
     * The subset of IDENTITY_FIELDS that actually varies by source - brand
     * and model are deliberately excluded even though they're part of the
     * completeness calculation below: every source always supplies both
     * (they're required to match/create the phone at all), so including
     * them here would make the hasAny() guard in recalculateConfidence()
     * always pass and defeat its purpose. See that method for why this
     * guard exists.
     */
    protected const IDENTITY_TRIGGER_FIELDS = ['announced_date', 'release_date', 'status'];

    protected const SPEC_FIELDS = [
        'processor', 'cpu', 'gpu', 'display_size', 'display_resolution', 'display_panel_type',
        'display_refresh_rate', 'main_camera', 'front_camera', 'battery_capacity_mah', 'charging_speed_w',
        'network_5g', 'usb_type', 'sim_config', 'weight_g', 'os',
    ];

    protected const SOFTWARE_FIELDS = ['os', 'os_update_years', 'security_update_years'];

    public function __construct(
        protected DuplicateDetector $duplicates,
        protected ConfidenceCalculator $confidence,
        protected ConflictDetector $conflicts,
        protected PriceAggregator $prices,
    ) {}

    public function run(
        PhoneSourceProvider $provider,
        PhoneSource $source,
        ImportRunTypeEnum $type,
        ?PhoneImportRun $resumeRun = null,
        ?int $userId = null,
        int $chunkSize = 20,
    ): PhoneImportRun {
        $run = $resumeRun ?? PhoneImportRun::create([
            'source_id' => $source->id,
            'type' => $type,
            'status' => ImportRunStatusEnum::RUNNING,
            'started_at' => now(),
            'triggered_by' => $userId,
        ]);

        if ($resumeRun) {
            $run->update(['status' => ImportRunStatusEnum::RUNNING, 'error_message' => null]);
        }

        $cursor = $run->cursor;

        try {
            do {
                $batch = $provider->fetchBatch($cursor, $chunkSize);

                foreach ($batch['items'] as $rawItem) {
                    $this->processItem($rawItem, $run, $source);
                }

                $cursor = $batch['cursor'];
                $run->update(['cursor' => $cursor]);
            } while (! $batch['done']);

            $source->update(['last_run_at' => now()]);

            $run->update([
                'status' => ImportRunStatusEnum::COMPLETED,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => ImportRunStatusEnum::PARTIAL,
                'error_message' => Str::limit($e->getMessage(), 2000),
            ]);

            throw $e;
        }

        return $run->fresh();
    }

    protected function processItem(array $raw, PhoneImportRun $run, PhoneSource $source): void
    {
        try {
            DB::transaction(function () use ($raw, $run, $source) {
                $run->increment('total_discovered');

                $normalized = $this->normalize($raw);
                $brand = $this->resolveBrand($normalized['brand']);
                $match = $this->matchPhone($brand, $normalized['model'], $normalized['specs'] ?? null);

                $record = PhoneImportRecord::create([
                    'import_run_id' => $run->id,
                    'source_id' => $source->id,
                    'external_ref' => $raw['external_ref'] ?? SpecNormalizer::phoneSlug($normalized['brand'], $normalized['model']),
                    'match_status' => MatchStatusEnum::from($match['status']),
                    'raw_payload' => $raw,
                    'normalized_payload' => $normalized,
                    'processed_at' => now(),
                ]);

                if ($match['status'] === 'needs_review') {
                    $this->flagPossibleDuplicate($record, $match, $brand);
                    $run->increment('total_flagged');

                    return;
                }

                $isNew = $match['status'] !== 'matched';
                $phone = $this->materialize($record, $brand, $normalized, $source, $isNew ? null : $match['phone']);

                $isNew ? $run->increment('total_created') : $run->increment('total_updated');
            });
        } catch (\Throwable $e) {
            $run->increment('total_failed');

            PhoneImportRecord::create([
                'import_run_id' => $run->id,
                'source_id' => $source->id,
                'external_ref' => $raw['external_ref'] ?? 'unknown',
                'match_status' => MatchStatusEnum::REJECTED,
                'raw_payload' => $raw,
                'error_message' => Str::limit($e->getMessage(), 2000),
                'processed_at' => now(),
            ]);
        }
    }

    /**
     * Create or update the phone + spec + variants + prices + availability
     * + network bands for an already-matched (or new) phone, then score
     * confidence and route to approved/needs_review. Public so the admin
     * "approve as new phone" review action can materialize a record that
     * was previously held back as a possible duplicate.
     */
    public function materialize(
        PhoneImportRecord $record,
        Brand $brand,
        array $normalized,
        PhoneSource $source,
        ?Phone $existingPhone,
    ): Phone {
        $isNewPhone = $existingPhone === null;
        $phone = $this->upsertPhone($existingPhone, $brand, $normalized, $source);
        $record->update(['phone_id' => $phone->id]);

        $specCompleteness = $this->upsertSpec($phone, $normalized['specs'] ?? [], $source, $record);

        foreach ($normalized['variants'] ?? [] as $variantData) {
            $variant = $this->upsertVariant($phone, $variantData, $source);
            $this->upsertPrice($variant, $variantData, $source, $record);
            $this->upsertAvailability($variant, $variantData['store'] ?? null, $variantData['availability'] ?? null, $source);
        }

        $this->upsertNetworkBands($phone, $normalized['network_bands'] ?? [], $source);

        $scores = $this->recalculateConfidence($phone, $source, $normalized, $specCompleteness);

        $record->update(['confidence_score' => $scores['overall']]);

        $autoApproved = $this->confidence->shouldAutoApprove($scores['overall']);

        // Confidence gates publish visibility, not just at creation: a
        // phone that now clears the bar is (re)activated - e.g. a
        // previously-thin record that a later import completed - while a
        // brand-new phone that doesn't clear it is kept out of the public
        // catalogue (is_active = false) rather than published on the
        // strength of a review queue nobody may ever clear. An existing,
        // already-published phone is never retroactively hidden by a
        // later low-confidence update; only a not-yet-published phone's
        // own confidence ever turns visibility off.
        if ($autoApproved) {
            $phone->update(['is_active' => true]);
        } elseif ($isNewPhone) {
            $phone->update(['is_active' => false]);
        }

        if ($source->requires_review || ! $autoApproved) {
            $record->update(['match_status' => MatchStatusEnum::NEEDS_REVIEW]);
            $this->queueReview($phone, $record, $scores['overall']);
            $record->importRun()->first()?->increment('total_flagged');
        } else {
            $record->update(['match_status' => MatchStatusEnum::APPROVED]);
        }

        return $phone;
    }

    // -----------------------------------------------------------------
    // Normalization
    // -----------------------------------------------------------------

    protected function normalize(array $raw): array
    {
        $specsRaw = $raw['specs'] ?? [];
        [$height, $width, $thickness] = SpecNormalizer::dimensionsMm($specsRaw['dimensions'] ?? null);

        $specs = [
            'processor' => $specsRaw['processor'] ?? null,
            'chipset_manufacturer' => $specsRaw['chipset_manufacturer'] ?? null,
            'cpu' => $specsRaw['cpu'] ?? null,
            'gpu' => $specsRaw['gpu'] ?? null,
            'display_size' => SpecNormalizer::displaySizeInches($specsRaw['display_size'] ?? null),
            'display_resolution' => $specsRaw['display_resolution'] ?? null,
            'display_panel_type' => $specsRaw['display_panel_type'] ?? null,
            'display_refresh_rate' => SpecNormalizer::refreshRateHz($specsRaw['display_refresh_rate'] ?? null),
            'display_protection' => $specsRaw['display_protection'] ?? null,
            'display_brightness_nits' => SpecNormalizer::refreshRateHz($specsRaw['display_brightness_nits'] ?? null),
            'main_camera' => $specsRaw['main_camera'] ?? null,
            'ultrawide_camera' => $specsRaw['ultrawide_camera'] ?? null,
            'telephoto_camera' => $specsRaw['telephoto_camera'] ?? null,
            'macro_camera' => $specsRaw['macro_camera'] ?? null,
            'front_camera' => $specsRaw['front_camera'] ?? null,
            'camera_has_ois' => $this->optionalBoolean($specsRaw, 'camera_has_ois'),
            'video_recording' => $specsRaw['video_recording'] ?? null,
            'battery_capacity_mah' => SpecNormalizer::batteryMah($specsRaw['battery_capacity'] ?? null),
            'charging_speed_w' => SpecNormalizer::wattage($specsRaw['charging_speed'] ?? null),
            'wireless_charging_w' => SpecNormalizer::wattage($specsRaw['wireless_charging'] ?? null),
            'reverse_charging' => $this->optionalBoolean($specsRaw, 'reverse_charging'),
            'network_4g' => true,
            'network_5g' => $this->optionalBoolean($specsRaw, 'network_5g'),
            'wifi' => $specsRaw['wifi'] ?? null,
            'bluetooth_version' => $specsRaw['bluetooth_version'] ?? null,
            'nfc' => $this->optionalBoolean($specsRaw, 'nfc'),
            'usb_type' => $specsRaw['usb_type'] ?? null,
            'sim_config' => $specsRaw['sim_config'] ?? null,
            'height_mm' => $height,
            'width_mm' => $width,
            'thickness_mm' => $thickness,
            'weight_g' => SpecNormalizer::weightGrams($specsRaw['weight'] ?? null),
            'build_materials' => $specsRaw['build_materials'] ?? null,
            'ip_rating' => $specsRaw['ip_rating'] ?? null,
            'os' => $specsRaw['os'] ?? null,
            'current_os' => $specsRaw['current_os'] ?? $specsRaw['os'] ?? null,
            'os_update_years' => SpecNormalizer::wattage($specsRaw['os_update_years'] ?? null),
            'security_update_years' => SpecNormalizer::wattage($specsRaw['security_update_years'] ?? null),
        ];

        $variants = collect($raw['variants'] ?? [])->map(function (array $variant) {
            return [
                'ram_gb' => SpecNormalizer::ramGb($variant['ram'] ?? null),
                'storage_gb' => SpecNormalizer::storageGb($variant['storage'] ?? null),
                'storage_type' => $variant['storage_type'] ?? null,
                'color' => $variant['color'] ?? null,
                'region' => $variant['region'] ?? null,
                'is_official_bd' => SpecNormalizer::boolean($variant['is_official_bd'] ?? false),
                'official_bd_prices' => $this->normalizePriceObservations($variant['price']['official_bd'] ?? null, $variant),
                'unofficial_bd_prices' => $this->normalizePriceObservations($variant['price']['unofficial_bd'] ?? null, $variant),
                'availability' => $variant['availability'] ?? null,
                'store' => $variant['store'] ?? null,
            ];
        })->all();

        $networkBands = collect($raw['network_bands'] ?? [])
            ->mapWithKeys(fn ($bands, $type) => [$type => $bands])
            ->all();

        return [
            'brand' => SpecNormalizer::name($raw['brand']),
            'model' => SpecNormalizer::name($raw['model']),
            'model_number' => $raw['model_number'] ?? null,
            'announced_date' => SpecNormalizer::date($raw['announced_date'] ?? null)?->toDateString(),
            'release_date' => SpecNormalizer::date($raw['release_date'] ?? null)?->toDateString(),
            // Left null (not defaulted to 'available' here) when a source
            // doesn't state it, e.g. a price-only retailer feed - so
            // upsertPhone() can tell "unknown" apart from "known
            // available" and never overwrites an existing phone's real
            // status (discontinued, upcoming, ...) with a false default.
            // Only a brand-new phone record defaults to 'available'.
            'status' => $raw['status'] ?? null,
            'category' => $raw['category'] ?? null,
            'specs' => $specs,
            'variants' => $variants,
            'network_bands' => $networkBands,
        ];
    }

    /**
     * A price field (raw.variants[].price.official_bd / .unofficial_bd) is
     * either a single scalar amount (the common case: one known price for
     * this variant, attributed to the variant's own top-level store) or a
     * list of independent retailer observations - {amount, store,
     * source_url, warranty, availability} - for when several retailers'
     * prices for the exact same listing are known. Both shapes normalize
     * to the same observation list so the rest of the pipeline never has
     * to care which one a given source used.
     *
     * @return list<array{amount: ?float, store: ?string, source_url: ?string, warranty_type: ?string, availability: ?string}>
     */
    protected function normalizePriceObservations(mixed $raw, array $variant): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            // Either a list of retailer observations, or a single
            // {amount, store, ...} object - normalize both to a list.
            $entries = array_is_list($raw) ? $raw : [$raw];

            return collect($entries)
                ->map(function (mixed $entry) use ($variant) {
                    $entry = is_array($entry) ? $entry : ['amount' => $entry];

                    return [
                        'amount' => SpecNormalizer::amount($entry['amount'] ?? null),
                        'store' => $entry['store'] ?? $variant['store'] ?? null,
                        'source_url' => $entry['source_url'] ?? null,
                        'warranty_type' => $entry['warranty'] ?? $entry['warranty_type'] ?? null,
                        'availability' => $entry['availability'] ?? $variant['availability'] ?? null,
                    ];
                })
                ->filter(fn (array $observation) => $observation['amount'] !== null)
                ->values()
                ->all();
        }

        $amount = SpecNormalizer::amount($raw);

        if ($amount === null) {
            return [];
        }

        return [[
            'amount' => $amount,
            'store' => $variant['store'] ?? null,
            'source_url' => $variant['source_url'] ?? null,
            'warranty_type' => $variant['warranty'] ?? $variant['warranty_type'] ?? null,
            'availability' => $variant['availability'] ?? null,
        ]];
    }

    // -----------------------------------------------------------------
    // Matching
    // -----------------------------------------------------------------

    /**
     * SpecNormalizer::boolean() defaulted to `false` when a source simply
     * didn't mention a field (e.g. camera_has_ois, network_5g) - but
     * `false` is a real, storable claim ("this phone does NOT have this
     * feature"), not the same thing as "this source doesn't know". A
     * source that never looked at a field (a price-only retailer feed
     * being the clearest case) must produce null - "unknown" - so it
     * neither corrupts an existing true value with a false default nor
     * counts as this import having contributed real spec completeness
     * (see recalculateConfidence()'s and upsertSpec()'s equivalent
     * guards for WHY that distinction matters).
     */
    protected function optionalBoolean(array $specsRaw, string $key): ?bool
    {
        return array_key_exists($key, $specsRaw) ? SpecNormalizer::boolean($specsRaw[$key]) : null;
    }

    protected function resolveBrand(string $name): Brand
    {
        $slug = Str::slug($name);

        return Brand::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_active' => true]
        );
    }

    /**
     * The 'type' assigned here is only a starting suggestion for the
     * admin UI (see config('phone_pricing.known_retailer_types')) - it
     * never gates whether a listing counts as official or unofficial,
     * and is never touched again once the store already exists.
     */
    protected function resolveStore(string $name): PhoneStore
    {
        $slug = Str::slug($name);

        return PhoneStore::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'type' => config("phone_pricing.known_retailer_types.{$slug}", 'other'), 'is_active' => true]
        );
    }

    /**
     * @return array{status: string, phone?: Phone, score?: int}
     */
    protected function matchPhone(Brand $brand, string $modelName, ?array $incomingSpecs = null): array
    {
        $slug = SpecNormalizer::phoneSlug($brand->name, $modelName);

        $exact = Phone::query()->where('slug', $slug)->first();

        if ($exact) {
            return ['status' => 'matched', 'phone' => $exact];
        }

        $best = $this->duplicates->findBestMatch($brand, $modelName, $incomingSpecs);

        if (! $best) {
            return ['status' => 'new'];
        }

        $status = $this->duplicates->classify($best['score']);

        return $status === 'new'
            ? ['status' => 'new']
            : ['status' => $status, 'phone' => $best['phone'], 'score' => $best['score']];
    }

    protected function flagPossibleDuplicate(PhoneImportRecord $record, array $match, Brand $brand): void
    {
        // Mirrors queueReview()'s guard below: a re-import of the same source
        // (a --fresh re-run, a repeated nightly pass) re-evaluates every raw
        // record from scratch and would otherwise re-flag the exact same
        // ambiguous (incoming model, matched phone) pair every single time,
        // stacking up duplicate review rows for one genuine open question.
        $alreadyQueued = PhoneDataReview::query()
            ->where('matched_phone_id', $match['phone']->id)
            ->where('reason', 'possible_duplicate')
            ->where('status', 'pending')
            ->whereJsonContains('details->incoming_model', $record->normalized_payload['model'] ?? null)
            ->exists();

        if ($alreadyQueued) {
            return;
        }

        PhoneDataReview::create([
            'import_record_id' => $record->id,
            'matched_phone_id' => $match['phone']->id,
            'reason' => 'possible_duplicate',
            'similarity_score' => $match['score'],
            'status' => 'pending',
            'details' => [
                'brand' => $brand->name,
                'incoming_model' => $record->normalized_payload['model'] ?? null,
                'existing_model' => $match['phone']->name,
            ],
        ]);
    }

    // -----------------------------------------------------------------
    // Persistence
    // -----------------------------------------------------------------

    protected function upsertPhone(?Phone $phone, Brand $brand, array $normalized, PhoneSource $source): Phone
    {
        if ($phone) {
            // A partial-data source (e.g. a price/availability-only
            // Bangladesh retailer feed - see BangladeshRetailerSource)
            // simply doesn't know a phone's model_number/announced_date/
            // release_date/category/status. Overwriting an existing
            // phone's already-known identity fields with that source's
            // nulls would be exactly the "silently overwrite good data
            // with weaker data" failure mode - so an existing phone only
            // has a field touched when the new record actually supplies
            // a value for it.
            $updates = array_filter([
                'brand_id' => $brand->id,
                'name' => $normalized['model'],
                'slug' => SpecNormalizer::phoneSlug($brand->name, $normalized['model']),
                'model_number' => $normalized['model_number'],
                'announced_date' => $normalized['announced_date'],
                'release_date' => $normalized['release_date'],
                'status' => $normalized['status'],
                'category' => $normalized['category'],
            ], fn ($value) => $value !== null);

            $phone->update($updates + ['last_verified_at' => now()]);

            return $phone;
        }

        return Phone::create([
            'brand_id' => $brand->id,
            'name' => $normalized['model'],
            'slug' => SpecNormalizer::phoneSlug($brand->name, $normalized['model']),
            'model_number' => $normalized['model_number'],
            'announced_date' => $normalized['announced_date'],
            'release_date' => $normalized['release_date'],
            'status' => $normalized['status'] ?? 'available',
            'category' => $normalized['category'],
            'primary_source_id' => $source->id,
            'collected_at' => now(),
            'last_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * @return float completeness ratio (0-1) of the spec fields present in this record.
     */
    protected function upsertSpec(Phone $phone, array $specs, PhoneSource $source, PhoneImportRecord $record): float
    {
        $existing = PhoneSpec::query()->where('phone_id', $phone->id)->first();

        if ($existing) {
            foreach (self::CONFLICT_WATCHED_SPEC_FIELDS as $field) {
                if (! array_key_exists($field, $specs)) {
                    continue;
                }

                $existingSource = $existing->source_id ? PhoneSource::find($existing->source_id) : null;
                $conflict = $this->conflicts->evaluate($existing->{$field}, $existingSource, $specs[$field], $source);

                if (! $conflict) {
                    continue;
                }

                $this->recordConflict($phone, null, 'phone_specs', $field, $conflict, $existingSource, $source, $record);

                if ($conflict['action'] === 'flag') {
                    // Keep the existing value until a human resolves the conflict.
                    unset($specs[$field]);
                }
            }
        }

        $specs = array_filter($specs, fn ($value) => $value !== null);
        $completeness = $this->confidence->completeness($specs, self::SPEC_FIELDS);

        // A price/availability-only retailer feed contributes nothing
        // here (see recalculateConfidence()'s equivalent guard) - writing
        // source_id/collected_at on an existing spec row anyway would
        // misattribute "who last verified this phone's specs" to a
        // source that never looked at them.
        if (empty($specs) && $existing) {
            return $completeness;
        }

        $specs['source_id'] = $source->id;
        $specs['collected_at'] = now();

        PhoneSpec::query()->updateOrCreate(['phone_id' => $phone->id], $specs);

        return $completeness;
    }

    protected function recordConflict(
        Phone $phone,
        ?PhoneVariant $variant,
        string $table,
        string $field,
        array $conflict,
        ?PhoneSource $existingSource,
        PhoneSource $newSource,
        PhoneImportRecord $record,
    ): void {
        $status = $conflict['action'] === 'auto_resolve' ? 'auto_resolved' : 'open';

        PhoneDataConflict::create([
            'phone_id' => $phone->id,
            'phone_variant_id' => $variant?->id,
            'table_name' => $table,
            'field' => $field,
            'existing_value' => (string) $conflict['existing_value'],
            'existing_source_id' => $existingSource?->id,
            'new_value' => (string) $conflict['new_value'],
            'new_source_id' => $newSource->id,
            'import_record_id' => $record->id,
            'status' => $status,
            'resolved_value' => $conflict['action'] === 'auto_resolve' ? (string) $conflict['new_value'] : null,
            'resolved_at' => $conflict['action'] === 'auto_resolve' ? now() : null,
        ]);

        $record->importRun()->first()?->increment('total_conflicts');
    }

    protected function upsertVariant(Phone $phone, array $variantData, PhoneSource $source): PhoneVariant
    {
        $slug = SpecNormalizer::slug(
            $phone->slug,
            (string) ($variantData['ram_gb'] ?? ''),
            (string) ($variantData['storage_gb'] ?? ''),
            (string) ($variantData['color'] ?? ''),
            (string) ($variantData['region'] ?? '')
        );

        $variant = PhoneVariant::query()->updateOrCreate(
            [
                // Identity is brand+model (via phone_id) + RAM + storage +
                // region - exactly the dimensions a Bangladesh retailer's
                // own price actually varies by. Color is NOT part of
                // identity: a retailer prices every color of a given
                // RAM/storage/region combo the same, so keying on color
                // too would let one retailer's feed (which may name a
                // different color than another's) fragment one real SKU's
                // price history across multiple phone_variants rows. See
                // the migration that dropped color from this unique index.
                'phone_id' => $phone->id,
                'ram_gb' => $variantData['ram_gb'],
                'storage_gb' => $variantData['storage_gb'],
                'region' => $variantData['region'],
            ],
            [
                'slug' => $slug,
                'storage_type' => $variantData['storage_type'] ?? null,
                'is_official_bd' => $variantData['is_official_bd'] ?? false,
                'status' => 'available',
                'source_id' => $source->id,
                'collected_at' => now(),
                'is_active' => true,
            ]
        );

        // Color is descriptive only (see above) - a source that doesn't
        // name one (e.g. a price-only retailer feed reporting a price
        // that applies across every color) must never blank out a color
        // an earlier, more descriptive source already recorded.
        if (! empty($variantData['color']) && $variant->color !== $variantData['color']) {
            $variant->update(['color' => $variantData['color']]);
        }

        return $variant;
    }

    /**
     * Writes every retailer observation for both price types, then
     * recalculates the outlier-resistant current market price for each
     * type that actually received new data this run. A single malformed
     * observation is isolated (routed to the review queue, see
     * flagPriceOutlier()) rather than allowed to corrupt the market price
     * every other candidate/consumer of this variant reads.
     */
    protected function upsertPrice(PhoneVariant $variant, array $variantData, PhoneSource $source, PhoneImportRecord $record): void
    {
        foreach (PriceTypeEnum::cases() as $priceType) {
            $key = $priceType === PriceTypeEnum::OFFICIAL_BD ? 'official_bd_prices' : 'unofficial_bd_prices';
            $observations = $variantData[$key] ?? [];

            foreach ($observations as $observation) {
                $this->upsertOnePrice($variant, $source, $priceType, $observation, $record);
            }

            if (! empty($observations)) {
                $this->prices->recalculate($variant, $priceType);
            }
        }
    }

    /**
     * @param  array{amount: ?float, store: ?string, source_url: ?string, warranty_type: ?string, availability: ?string}  $observation
     */
    protected function upsertOnePrice(PhoneVariant $variant, PhoneSource $source, PriceTypeEnum $priceType, array $observation, PhoneImportRecord $record): void
    {
        $amount = $observation['amount'];

        if ($amount === null) {
            return;
        }

        $store = $observation['store'] ? $this->resolveStore($observation['store']) : null;

        $reference = PhoneMarketPrice::query()
            ->where('phone_variant_id', $variant->id)
            ->where('price_type', $priceType->value)
            ->first();

        if (! $this->prices->isSane($amount, $reference)) {
            $this->flagPriceOutlier($variant, $store, $priceType, $amount, $reference, $source, $record);

            return;
        }

        $existing = PhonePrice::query()
            ->where('phone_variant_id', $variant->id)
            ->where('store_id', $store?->id)
            ->where('price_type', $priceType->value)
            ->first();

        // Only write history when the amount actually changed - avoid meaningless
        // duplicate history rows when a nightly re-check finds the same price.
        if (! $existing || $existing->amount !== number_format($amount, 2, '.', '')) {
            PhonePriceHistory::create([
                'phone_variant_id' => $variant->id,
                'store_id' => $store?->id,
                'price_type' => $priceType->value,
                'amount' => $amount,
                'previous_amount' => $existing?->amount,
                'source_id' => $source->id,
                'changed_at' => now(),
            ]);
        }

        PhonePrice::query()->updateOrCreate(
            ['phone_variant_id' => $variant->id, 'store_id' => $store?->id, 'price_type' => $priceType->value],
            [
                'amount' => $amount,
                'currency' => 'BDT',
                'source_url' => $observation['source_url'] ?? null,
                'warranty_type' => $observation['warranty_type'] ?? null,
                'source_id' => $source->id,
                'confidence' => $source->reliability_score,
                'collected_at' => now(),
                'last_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $this->upsertAvailability($variant, $observation['store'], $observation['availability'] ?? null, $source);
    }

    /**
     * A price observation whose amount is wildly implausible given the
     * market price already on record (see PriceAggregator::isSane()) is
     * never written to phone_prices - it's preserved in a review record
     * instead, so a human can investigate without the existing
     * trustworthy price ever being silently overwritten.
     */
    protected function flagPriceOutlier(
        PhoneVariant $variant,
        ?PhoneStore $store,
        PriceTypeEnum $priceType,
        float $amount,
        ?PhoneMarketPrice $reference,
        PhoneSource $source,
        PhoneImportRecord $record,
    ): void {
        PhoneDataReview::create([
            'phone_id' => $variant->phone_id,
            'phone_variant_id' => $variant->id,
            'import_record_id' => $record->id,
            'reason' => ReviewReasonEnum::PRICE_OUTLIER->value,
            'status' => ReviewStatusEnum::PENDING->value,
            'details' => [
                'price_type' => $priceType->value,
                'submitted_amount' => $amount,
                'reference_price' => $reference?->price !== null ? (float) $reference->price : null,
                'store' => $store?->name,
                'source' => $source->name,
            ],
        ]);

        $record->importRun()->first()?->increment('total_flagged');
    }

    protected function upsertAvailability(PhoneVariant $variant, ?string $storeName, ?string $status, PhoneSource $source): void
    {
        if (empty($status)) {
            return;
        }

        $store = ! empty($storeName) ? $this->resolveStore($storeName) : null;

        PhoneAvailability::query()->updateOrCreate(
            ['phone_variant_id' => $variant->id, 'store_id' => $store?->id],
            [
                'status' => $status,
                'source_id' => $source->id,
                'confidence' => $source->reliability_score,
                'collected_at' => now(),
            ]
        );
    }

    protected function upsertNetworkBands(Phone $phone, array $bands, PhoneSource $source): void
    {
        foreach ($bands as $type => $value) {
            if (! $value) {
                continue;
            }

            PhoneNetworkBand::query()->updateOrCreate(
                ['phone_id' => $phone->id, 'network_type' => $type],
                ['bands' => $value, 'source_id' => $source->id]
            );
        }
    }

    // -----------------------------------------------------------------
    // Confidence
    // -----------------------------------------------------------------

    /**
     * @return array{identity: int, spec: int, software: int, overall: int}
     */
    protected function recalculateConfidence(Phone $phone, PhoneSource $source, array $normalized, float $specCompleteness): array
    {
        $specsPayload = $normalized['specs'] ?? [];
        $softwareCompleteness = $this->confidence->completeness($specsPayload, self::SOFTWARE_FIELDS);

        // A price/availability-only retailer feed (see
        // App\Services\PhoneImport\Sources\BangladeshRetailerSource)
        // legitimately supplies zero spec/software/announced-release-status
        // fields - that's not evidence the phone's data got worse, just
        // that this particular source doesn't speak to it. Recomputing a
        // category from an all-null payload would drag a previously
        // well-documented phone's confidence down to whatever a pure
        // reliability score implies every time a pricing-only source runs
        // (verified: a real catalogue-wide retailer refresh pass silently
        // dragged dozens of ai_research_2026-sourced phones' identity_confidence
        // from ~75-83 down to 61-67, purely from repeated price-only
        // updates that never touched identity at all). Only overwrite a
        // category when this import actually contributed at least one of
        // its fields; otherwise the phone's existing score for that
        // category stands.
        $identity = $this->hasAny($normalized, self::IDENTITY_TRIGGER_FIELDS)
            ? $this->confidence->categoryScore($source, $this->confidence->completeness($normalized, self::IDENTITY_FIELDS))
            : (int) ($phone->identity_confidence ?? 0);

        $spec = $this->hasAny($specsPayload, self::SPEC_FIELDS)
            ? $this->confidence->categoryScore($source, $specCompleteness)
            : (int) ($phone->spec_confidence ?? 0);

        $software = $this->hasAny($specsPayload, self::SOFTWARE_FIELDS)
            ? $this->confidence->categoryScore($source, $softwareCompleteness)
            : (int) ($phone->software_confidence ?? 0);

        $overall = $this->confidence->overall($identity, $spec, $software);

        $phone->update([
            'identity_confidence' => $identity,
            'spec_confidence' => $spec,
            'software_confidence' => $software,
            'overall_confidence' => $overall,
            'confidence_calculated_at' => now(),
        ]);

        return compact('identity', 'spec', 'software', 'overall');
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $keys
     */
    protected function hasAny(array $fields, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $fields) && $fields[$key] !== null && $fields[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    protected function queueReview(Phone $phone, PhoneImportRecord $record, int $score): void
    {
        // Once a human/agent has looked at this phone's low_confidence
        // question ONE time (approved or rejected - any resolved status,
        // not just a still-pending row), don't re-litigate it on every
        // later reimport of the same low-reliability source data. Without
        // this, a source with requires_review=true (e.g. seed_dataset)
        // re-flags the exact same already-decided phone forever, since its
        // own confidence never changes on a repeat import. A genuinely
        // richer later import can still clear the auto-approve bar on its
        // own merit without ever reaching this method at all (see the
        // caller's `$autoApproved` check).
        $alreadyDecided = PhoneDataReview::query()
            ->where('phone_id', $phone->id)
            ->where('reason', 'low_confidence')
            ->exists();

        if ($alreadyDecided) {
            return;
        }

        PhoneDataReview::create([
            'phone_id' => $phone->id,
            'import_record_id' => $record->id,
            'reason' => 'low_confidence',
            'status' => 'pending',
            'details' => ['overall_confidence' => $score],
        ]);
    }
}
