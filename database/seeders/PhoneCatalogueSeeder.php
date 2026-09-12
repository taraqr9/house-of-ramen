<?php

namespace Database\Seeders;

use App\Enums\PriceTypeEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneAvailability;
use App\Models\PhoneImage;
use App\Models\PhoneNetworkBand;
use App\Models\PhonePrice;
use App\Models\PhoneSource;
use App\Models\PhoneSpec;
use App\Models\PhoneStore;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\PhoneSourceRegistry;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Loads the committed catalogue snapshot (database/seed-data/catalogue/,
 * produced by `phones:export-catalogue`) into the database - brands,
 * phones, specs, variants, network bands, prices, images (copying the
 * actual files into storage/app/public/phones/), and the resolved review
 * history behind each phone. This is what lets `php artisan migrate:fresh
 * --seed` reproduce the full, already-reviewed catalogue on a brand-new
 * machine with no network access - the snapshot only ever contains
 * phones that had zero pending reviews at export time (see
 * ExportPhoneCatalogueCommand), so nothing seeded here needs further
 * review resolution.
 *
 * phone_market_prices is deliberately NOT read from the snapshot (it
 * isn't even exported - see ExportPhoneCatalogueCommand::exportPhone()).
 * It is derived data, so seedVariant() recomputes it from the
 * just-seeded phone_prices rows via the same PriceAggregator the admin
 * panel and import pipeline use (isSane/MAD-outlier rules, staleness
 * window, official/unofficial semantics - all untouched, all reused, not
 * reimplemented here). Seeding a price is treated as re-verifying it
 * right now (last_verified_at/collected_at are stamped with this run's
 * timestamp, $seededAt), so a freshly-deployed snapshot is never
 * immediately "too stale" for PriceAggregator to count.
 *
 * phone_price_history is deliberately left untouched by this seeder: a
 * bulk reseed has no reliable way to know whether an amount genuinely
 * changed "just now" versus was already known, so inventing history
 * rows here would fabricate change events that didn't really happen at
 * that moment. Real price changes are still recorded wherever they
 * actually occur - the admin panel and phones:attach-price, both of
 * which already write phone_price_history correctly.
 *
 * Idempotent: every table is upserted by a stable natural key (slug, or
 * an explicit unique combo matching the DB constraint), never raw
 * auto-increment ID trust, so re-running `db:seed` never creates
 * duplicates. Uses query-builder upserts (not Eloquent create) throughout
 * for speed across ~500+ phones and their nested rows.
 */
class PhoneCatalogueSeeder extends Seeder
{
    protected string $directory;

    protected string $imagesDirectory;

    protected PriceAggregator $priceAggregator;

    /**
     * Captured once for the whole run (not per-row) so every price seeded
     * in this pass shares the exact same "verified as of" instant.
     */
    protected Carbon $seededAt;

    public function run(): void
    {
        $this->directory = config('phone_catalogue_snapshot.directory');
        $this->imagesDirectory = $this->directory.'/images';
        $this->priceAggregator = app(PriceAggregator::class);
        $this->seededAt = Carbon::now();

        if (! File::isDirectory($this->directory)) {
            $this->command?->warn('No catalogue snapshot found at database/seed-data/catalogue - skipping. Run phones:export-catalogue after building one, or phones:import for a network-based catalogue.');

            return;
        }

        // Registers every phone_sources row from config/phone_sources.php
        // (including the non-fetchable provenance-only keys the seeded
        // images/prices reference, e.g. 'manual_research',
        // 'wikimedia_commons', 'seed_dataset') - pure DB upsert, no network.
        app(PhoneSourceRegistry::class)->sync();

        if (! Storage::disk('public')->exists('phones')) {
            Storage::disk('public')->makeDirectory('phones');
        }

        $this->seedBrands();
        $this->seedStores();

        $brandFiles = collect(File::files($this->directory))
            ->filter(fn ($file) => $file->getExtension() === 'json' && ! in_array($file->getFilename(), ['brands.json', 'stores.json'], true))
            ->sortBy(fn ($file) => $file->getFilename());

        $totalPhones = 0;

        foreach ($brandFiles as $file) {
            $payload = json_decode(File::get($file->getPathname()), true);
            $brand = Brand::query()->where('slug', $payload['brand_slug'])->first();

            if (! $brand) {
                $this->command?->warn("Skipping {$file->getFilename()}: brand '{$payload['brand_slug']}' not found (seed brands.json first).");

                continue;
            }

            foreach ($payload['phones'] as $phoneData) {
                $this->seedPhone($brand, $phoneData);
                $totalPhones++;
            }
        }

        $this->command?->info("PhoneCatalogueSeeder: {$totalPhones} phones loaded from committed snapshot.");
    }

    protected function seedBrands(): void
    {
        $path = "{$this->directory}/brands.json";

        if (! File::exists($path)) {
            return;
        }

        $brands = json_decode(File::get($path), true)['brands'] ?? [];

        // Pass 1: create/update every brand without the self-referential
        // parent link, so a parent brand always exists by the time pass 2
        // resolves it regardless of file order.
        foreach ($brands as $data) {
            Brand::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'country' => $data['country'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                ]
            );
        }

        foreach ($brands as $data) {
            if (empty($data['parent_brand_slug'])) {
                continue;
            }

            $parentId = Brand::query()->where('slug', $data['parent_brand_slug'])->value('id');

            if ($parentId) {
                Brand::query()->where('slug', $data['slug'])->update(['parent_brand_id' => $parentId]);
            }
        }
    }

    protected function seedStores(): void
    {
        $path = "{$this->directory}/stores.json";

        if (! File::exists($path)) {
            return;
        }

        $stores = json_decode(File::get($path), true)['stores'] ?? [];

        foreach ($stores as $data) {
            PhoneStore::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'website_url' => $data['website_url'] ?? null,
                    'type' => $data['type'] ?? 'other',
                    'is_active' => $data['is_active'] ?? true,
                ]
            );
        }
    }

    protected function seedPhone(Brand $brand, array $data): void
    {
        $phone = Phone::query()->updateOrCreate(
            ['slug' => $data['slug']],
            [
                'brand_id' => $brand->id,
                'name' => $data['name'],
                'model_number' => $data['model_number'] ?? null,
                'announced_date' => $data['announced_date'] ?? null,
                'release_date' => $data['release_date'] ?? null,
                'status' => $data['status'] ?? 'available',
                'category' => $data['category'] ?? null,
                'summary' => $data['summary'] ?? null,
                'is_ai_generated_summary' => $data['is_ai_generated_summary'] ?? false,
                'identity_confidence' => $data['identity_confidence'] ?? null,
                'spec_confidence' => $data['spec_confidence'] ?? null,
                'software_confidence' => $data['software_confidence'] ?? null,
                'overall_confidence' => $data['overall_confidence'] ?? null,
                'is_active' => $data['is_active'] ?? false,
            ]
        );

        if ($data['spec'] ?? null) {
            PhoneSpec::query()->updateOrCreate(['phone_id' => $phone->id], $data['spec']);
        }

        foreach ($data['network_bands'] ?? [] as $band) {
            PhoneNetworkBand::query()->updateOrCreate(
                ['phone_id' => $phone->id, 'network_type' => $band['network_type']],
                ['bands' => $band['bands'] ?? null]
            );
        }

        foreach ($data['variants'] ?? [] as $variantData) {
            $this->seedVariant($phone, $variantData);
        }

        foreach ($data['images'] ?? [] as $imageData) {
            $this->seedImage($phone, $imageData);
        }

        foreach ($data['reviews'] ?? [] as $reviewData) {
            // Keyed on reason+status+created_at, not just reason+status - a
            // phone can legitimately have more than one review sharing the
            // same reason and status (e.g. two separate nightly
            // collect-images runs each raising their own image_needs_review
            // flag before a later dedup guard was added), and created_at is
            // the field that reliably tells them apart (verified against
            // every such group in the live dataset before relying on it).
            // Must be a genuine Carbon instance, not the raw JSON string -
            // only a DateTimeInterface value gets reformatted to the active
            // connection's native date format before binding
            // (Connection::prepareBindings()), so a plain ISO8601 string
            // compares unreliably against whatever format the DB driver
            // actually stored (works by accident on MySQL's lenient
            // implicit parsing, silently fails - creating a duplicate
            // instead of matching - on SQLite's strict text comparison).
            // ->setTimezone(...) matters, not just ->parse(): the DB column
            // is naive (no stored offset), and Eloquent's own date cast
            // re-interprets whatever wall-clock string it finds there in
            // config('app.timezone') on every future read - binding a
            // still-UTC-offset Carbon instance would silently store the
            // wrong wall-clock time and shift the value by the zone
            // difference every time it's read back.
            $reviewCreatedAt = isset($reviewData['created_at'])
                ? Carbon::parse($reviewData['created_at'])->setTimezone(config('app.timezone'))
                : null;

            $phone->reviews()->updateOrCreate(
                [
                    'reason' => $reviewData['reason'],
                    'status' => $reviewData['status'],
                    'created_at' => $reviewCreatedAt,
                ],
                [
                    'similarity_score' => $reviewData['similarity_score'] ?? null,
                    'resolution_note' => $reviewData['resolution_note'] ?? null,
                    'reviewed_at' => $reviewData['reviewed_at'] ?? null,
                ]
            );
        }
    }

    protected function seedVariant(Phone $phone, array $data): void
    {
        $variant = PhoneVariant::query()->updateOrCreate(
            ['slug' => $data['slug']],
            [
                'phone_id' => $phone->id,
                'ram_gb' => $data['ram_gb'] ?? null,
                'storage_gb' => $data['storage_gb'] ?? null,
                'storage_type' => $data['storage_type'] ?? null,
                'expandable_storage' => $data['expandable_storage'] ?? false,
                'expandable_storage_max_gb' => $data['expandable_storage_max_gb'] ?? null,
                'color' => $data['color'] ?? null,
                'region' => $data['region'] ?? null,
                'sku' => $data['sku'] ?? null,
                'is_official_bd' => $data['is_official_bd'] ?? false,
                'status' => $data['status'] ?? 'available',
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        foreach ($data['prices'] ?? [] as $priceData) {
            $storeId = $priceData['store_slug']
                ? PhoneStore::query()->where('slug', $priceData['store_slug'])->value('id')
                : null;

            PhonePrice::query()->updateOrCreate(
                [
                    'phone_variant_id' => $variant->id,
                    'store_id' => $storeId,
                    'price_type' => $priceData['price_type'],
                ],
                [
                    'amount' => $priceData['amount'],
                    'currency' => $priceData['currency'] ?? 'BDT',
                    'confidence' => $priceData['confidence'] ?? null,
                    'is_active' => true,
                    // A seeded price is, by definition, whatever the
                    // committed snapshot currently asserts is correct -
                    // stamping "verified now" is what lets PriceAggregator
                    // (which only trusts observations newer than
                    // config('phone_pricing.stale_after_days')) count it
                    // immediately, rather than silently dropping every
                    // freshly-seeded price for having a null/old
                    // last_verified_at.
                    'collected_at' => $this->seededAt,
                    'last_verified_at' => $this->seededAt,
                ]
            );
        }

        // phone_market_prices is derived, never trusted from the snapshot
        // (see this class's docblock) - recompute it from the phone_prices
        // rows just seeded above, via the exact same aggregation rules
        // (MAD outlier rejection, staleness, official/unofficial
        // semantics) the admin panel and import pipeline already use.
        // Runs for every price type, not just the ones present in this
        // variant's JSON, so a market price left over from a type that no
        // longer has any seeded observation is correctly cleared rather
        // than lingering stale forever (see PriceAggregator::recalculate()
        // - it deletes the row when there is nothing fresh to aggregate).
        foreach (PriceTypeEnum::cases() as $priceType) {
            $this->priceAggregator->recalculate($variant, $priceType);
        }

        foreach ($data['availabilities'] ?? [] as $availabilityData) {
            $storeId = ($availabilityData['store_slug'] ?? null)
                ? PhoneStore::query()->where('slug', $availabilityData['store_slug'])->value('id')
                : null;

            $sourceId = ($availabilityData['source_key'] ?? null)
                ? PhoneSource::query()->where('key', $availabilityData['source_key'])->value('id')
                : null;

            PhoneAvailability::query()->updateOrCreate(
                ['phone_variant_id' => $variant->id, 'store_id' => $storeId],
                [
                    'status' => $availabilityData['status'] ?? 'in_stock',
                    'source_id' => $sourceId,
                    'confidence' => $availabilityData['confidence'] ?? null,
                    'collected_at' => $availabilityData['collected_at'] ?? null,
                ]
            );
        }
    }

    protected function seedImage(Phone $phone, array $data): void
    {
        if (empty($data['seed_file'])) {
            return;
        }

        $sourcePath = "{$this->imagesDirectory}/{$data['seed_file']}";

        if (! File::exists($sourcePath)) {
            $this->command?->warn("Missing seed image file for {$phone->slug}: {$data['seed_file']} - skipping this image row.");

            return;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'webp';
        $storagePath = "phones/{$phone->id}/".basename($data['seed_file']);

        if (! Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->put($storagePath, File::get($sourcePath));
        }

        $sourceId = ($data['source_key'] ?? null)
            ? PhoneSource::query()->where('key', $data['source_key'])->value('id')
            : null;

        if ($data['is_primary'] ?? false) {
            PhoneImage::query()->where('phone_id', $phone->id)->where('is_primary', true)->update(['is_primary' => false]);
        }

        PhoneImage::query()->updateOrCreate(
            ['phone_id' => $phone->id, 'path' => $storagePath],
            [
                'is_primary' => $data['is_primary'] ?? false,
                'sort_order' => $data['sort_order'] ?? 0,
                'disk' => 'public',
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'mime_type' => $data['mime_type'] ?? "image/{$extension}",
                'source_id' => $sourceId,
                'source_url' => $data['source_url'] ?? null,
                'license' => $data['license'] ?? null,
                'attribution' => $data['attribution'] ?? null,
                'match_confidence' => $data['match_confidence'] ?? null,
                'status' => $data['status'] ?? 'verified',
            ]
        );
    }
}
