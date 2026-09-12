<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Snapshots the current, fully-resolved catalogue state into committed
 * seed files (database/seed-data/catalogue/) so a fresh install can
 * reproduce it via PhoneCatalogueSeeder without hitting the network or
 * re-running the import/review/image pipelines. Only phones with ZERO
 * pending phone_data_reviews are included - a phone still awaiting
 * evidence-based review is deliberately left out of the shipped snapshot
 * rather than force-included in a half-decided state (re-run this command
 * after resolving more reviews to grow the snapshot). One JSON file per
 * brand (mirrors the existing database/seed-data/*.json convention) plus
 * a shared images/ directory of the actual verified image files.
 */
class ExportPhoneCatalogueCommand extends Command
{
    protected $signature = 'phones:export-catalogue
        {--brand= : Limit export to one brand slug (for incremental/parallel snapshot updates).}';

    protected $description = 'Snapshot the current fully-reviewed phone catalogue (specs, variants, prices, images, resolved reviews) into committed seed-data files.';

    public function handle(): int
    {
        $directory = config('phone_catalogue_snapshot.directory');
        $imagesDirectory = $directory.'/images';
        File::ensureDirectoryExists($directory);
        File::ensureDirectoryExists($imagesDirectory);

        $brandsQuery = Brand::query()->orderBy('slug');

        if ($brandSlug = $this->option('brand')) {
            $brandsQuery->where('slug', $brandSlug);
        }

        $brands = $brandsQuery->get();

        if ($brands->isEmpty()) {
            $this->warn('No matching brand(s) to export.');

            return self::SUCCESS;
        }

        $stores = PhoneStore::query()->orderBy('slug')->get()->keyBy('id');

        File::put($directory.'/brands.json', $this->jsonEncode([
            'brands' => Brand::query()->orderBy('slug')->get()->map(fn (Brand $brand) => [
                'slug' => $brand->slug,
                'name' => $brand->name,
                'country' => $brand->country,
                'parent_brand_slug' => $brand->parentBrand?->slug,
                'is_active' => $brand->is_active,
            ])->all(),
        ]));

        File::put($directory.'/stores.json', $this->jsonEncode([
            'stores' => $stores->values()->map(fn (PhoneStore $store) => [
                'slug' => $store->slug,
                'name' => $store->name,
                'website_url' => $store->website_url,
                'type' => $store->type,
                'is_active' => $store->is_active,
            ])->all(),
        ]));

        $totalExported = 0;
        $totalSkippedPending = 0;

        foreach ($brands as $brand) {
            $eligiblePhones = Phone::query()
                ->where('brand_id', $brand->id)
                ->whereDoesntHave('reviews', fn ($q) => $q->where('status', 'pending'))
                ->with([
                    'spec',
                    // Every nested collection below is given an explicit,
                    // content-based order (never left to unspecified DB/
                    // engine iteration order) so two exports of unchanged
                    // data are byte-identical - prices/availabilities are
                    // additionally sorted in PHP further down (their
                    // exported order key - store slug - isn't a query
                    // column, since store is a related model's attribute).
                    'networkBands' => fn ($q) => $q->orderBy('network_type'),
                    'variants' => fn ($q) => $q->orderBy('ram_gb')->orderBy('storage_gb')->orderBy('region')->orderBy('slug'),
                    'variants.prices.store',
                    'variants.availabilities.store',
                    'variants.availabilities.source',
                    // Phone::images() already orders by sort_order; add id
                    // only as a tiebreaker for when sort_order ties (e.g.
                    // two images imported in the same batch) - id is used
                    // here purely for ORDER BY stability, never written
                    // into the exported payload itself (see exportPhone()'s
                    // image filename, which uses the resulting position
                    // instead of the raw id).
                    'images' => fn ($q) => $q->orderBy('id'),
                    'reviews' => fn ($q) => $q->orderBy('reason')->orderBy('status')->orderBy('created_at'),
                ])
                ->orderBy('slug')
                ->get();

            $skipped = Phone::query()->where('brand_id', $brand->id)
                ->whereHas('reviews', fn ($q) => $q->where('status', 'pending'))
                ->count();

            $totalSkippedPending += $skipped;

            $this->line("{$brand->name}: exported {$eligiblePhones->count()}, skipped {$skipped} (pending review).");

            if ($eligiblePhones->isEmpty()) {
                continue;
            }

            $payload = [
                'brand_slug' => $brand->slug,
                'phones' => $eligiblePhones->map(fn (Phone $phone) => $this->exportPhone($phone, $imagesDirectory))->all(),
            ];

            File::put("{$directory}/{$brand->slug}.json", $this->jsonEncode($payload));

            $totalExported += $eligiblePhones->count();
        }

        $this->newLine();
        $this->info("Exported {$totalExported} phones. Skipped {$totalSkippedPending} still awaiting review resolution.");

        return self::SUCCESS;
    }

    protected function exportPhone(Phone $phone, string $imagesDirectory): array
    {
        $spec = $phone->spec;

        return [
            'slug' => $phone->slug,
            'name' => $phone->name,
            'model_number' => $phone->model_number,
            'announced_date' => $phone->announced_date?->toDateString(),
            'release_date' => $phone->release_date?->toDateString(),
            'status' => $phone->status?->value,
            'category' => $phone->category,
            'summary' => $phone->summary,
            'is_ai_generated_summary' => $phone->is_ai_generated_summary,
            'identity_confidence' => $phone->identity_confidence,
            'spec_confidence' => $phone->spec_confidence,
            'software_confidence' => $phone->software_confidence,
            'overall_confidence' => $phone->overall_confidence,
            'is_active' => $phone->is_active,
            'spec' => $spec ? [
                'processor' => $spec->processor,
                'chipset_manufacturer' => $spec->chipset_manufacturer,
                'cpu' => $spec->cpu,
                'gpu' => $spec->gpu,
                'display_size' => $spec->display_size,
                'display_resolution' => $spec->display_resolution,
                'display_panel_type' => $spec->display_panel_type,
                'display_refresh_rate' => $spec->display_refresh_rate,
                'display_protection' => $spec->display_protection,
                'display_brightness_nits' => $spec->display_brightness_nits,
                'main_camera' => $spec->main_camera,
                'ultrawide_camera' => $spec->ultrawide_camera,
                'telephoto_camera' => $spec->telephoto_camera,
                'macro_camera' => $spec->macro_camera,
                'front_camera' => $spec->front_camera,
                'camera_has_ois' => $spec->camera_has_ois,
                'video_recording' => $spec->video_recording,
                'battery_capacity_mah' => $spec->battery_capacity_mah,
                'charging_speed_w' => $spec->charging_speed_w,
                'wireless_charging_w' => $spec->wireless_charging_w,
                'reverse_charging' => $spec->reverse_charging,
                'network_4g' => $spec->network_4g,
                'network_5g' => $spec->network_5g,
                'wifi' => $spec->wifi,
                'bluetooth_version' => $spec->bluetooth_version,
                'nfc' => $spec->nfc,
                'usb_type' => $spec->usb_type,
                'sim_config' => $spec->sim_config,
                'height_mm' => $spec->height_mm,
                'width_mm' => $spec->width_mm,
                'thickness_mm' => $spec->thickness_mm,
                'weight_g' => $spec->weight_g,
                'build_materials' => $spec->build_materials,
                'ip_rating' => $spec->ip_rating,
                'os' => $spec->os,
                'current_os' => $spec->current_os,
                'os_update_years' => $spec->os_update_years,
                'security_update_years' => $spec->security_update_years,
                'estimated_eol_date' => $spec->estimated_eol_date?->toDateString(),
            ] : null,
            'network_bands' => $phone->networkBands->map(fn ($band) => [
                'network_type' => $band->network_type,
                'bands' => $band->bands,
            ])->all(),
            'variants' => $phone->variants->map(fn ($variant) => [
                'slug' => $variant->slug,
                'ram_gb' => $variant->ram_gb,
                'storage_gb' => $variant->storage_gb,
                'storage_type' => $variant->storage_type,
                'expandable_storage' => $variant->expandable_storage,
                'expandable_storage_max_gb' => $variant->expandable_storage_max_gb,
                'color' => $variant->color,
                'region' => $variant->region,
                'sku' => $variant->sku,
                'is_official_bd' => $variant->is_official_bd,
                'status' => $variant->status,
                'is_active' => $variant->is_active,
                'prices' => $variant->prices->where('is_active', true)
                    ->sortBy(fn ($price) => ($price->store?->slug ?? '').'|'.($price->price_type?->value ?? ''))
                    ->values()
                    ->map(fn ($price) => [
                        'store_slug' => $price->store?->slug,
                        'price_type' => $price->price_type?->value,
                        'amount' => (string) $price->amount,
                        'currency' => $price->currency,
                        'confidence' => $price->confidence,
                    ])->all(),
                // phone_market_prices is intentionally NOT exported: it is
                // fully derived from phone_prices via PriceAggregator (see
                // PhoneCatalogueSeeder::seedVariant()), so snapshotting it
                // here would let a stale/hand-edited aggregate silently
                // diverge from the raw observations that are supposed to
                // be its only source of truth.
                'availabilities' => $variant->availabilities
                    ->sortBy(fn ($availability) => $availability->store?->slug ?? '')
                    ->values()
                    ->map(fn ($availability) => [
                        'store_slug' => $availability->store?->slug,
                        'status' => $availability->status?->value,
                        'source_key' => $availability->source?->key,
                        'confidence' => $availability->confidence,
                        'collected_at' => $availability->collected_at?->toIso8601String(),
                    ])->all(),
            ])->all(),
            'images' => $phone->images->values()->map(function ($image, int $index) use ($phone, $imagesDirectory) {
                $seedFilename = null;

                if ($image->path && Storage::disk('public')->exists($image->path)) {
                    $extension = pathinfo($image->path, PATHINFO_EXTENSION) ?: 'webp';
                    // Position within this phone's deterministically-ordered
                    // image list, not the raw database id - keeps the
                    // filename (and therefore the export) free of any
                    // environment-specific value, per phone_catalogue_snapshot's
                    // natural-key convention.
                    $seedFilename = "{$image->sort_order}-{$index}.{$extension}";
                    $destinationDir = "{$imagesDirectory}/{$phone->slug}";
                    File::ensureDirectoryExists($destinationDir);
                    File::copy(Storage::disk('public')->path($image->path), "{$destinationDir}/{$seedFilename}");
                }

                return [
                    'seed_file' => $seedFilename ? "{$phone->slug}/{$seedFilename}" : null,
                    'is_primary' => $image->is_primary,
                    'sort_order' => $image->sort_order,
                    'width' => $image->width,
                    'height' => $image->height,
                    'mime_type' => $image->mime_type,
                    'source_key' => $image->source?->key,
                    'source_url' => $image->source_url,
                    'license' => $image->license,
                    'attribution' => $image->attribution,
                    'match_confidence' => $image->match_confidence,
                    'status' => $image->status?->value,
                ];
            })->filter(fn ($image) => $image['seed_file'] !== null)->values()->all(),
            'reviews' => $phone->reviews->map(fn ($review) => [
                'reason' => $review->reason?->value,
                'status' => $review->status?->value,
                'similarity_score' => $review->similarity_score,
                'resolution_note' => $review->resolution_note,
                'reviewed_at' => $review->reviewed_at?->toIso8601String(),
                // Part of the seeder's upsert key alongside reason+status -
                // a phone can legitimately have more than one review with
                // the same reason+status (e.g. two separate nightly
                // collect-images runs each raising their own
                // image_needs_review flag before a later dedup guard was
                // added), and without a third distinguishing field those
                // rows collapse into one on every reseed. created_at is
                // reliably distinct even within an identical (reason,
                // status) group - verified against the full live dataset.
                'created_at' => $review->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    protected function jsonEncode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
}
