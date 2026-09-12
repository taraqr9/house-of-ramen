<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneAvailability;
use App\Models\PhoneDataReview;
use App\Models\PhoneImage;
use App\Models\PhoneMarketPrice;
use App\Models\PhonePrice;
use App\Models\PhoneVariant;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * The single, named entry point for rebuilding the complete Phone Kinbo
 * catalogue from the committed offline snapshot
 * (database/seed-data/catalogue/) - brands, phones, variants, specs,
 * prices, market prices, availability, review history, and the actual
 * verified image files, all in the correct dependency order. Wraps the
 * existing `db:seed` -> Database\Seeders\DatabaseSeeder pipeline (which
 * already runs AdminSeeder -> MenuSeeder -> PhoneCatalogueSeeder in that
 * order) rather than reimplementing it, so there is exactly one seeding
 * pipeline, not two competing ones.
 *
 * Purely local/offline: every step reads only from disk (the committed
 * JSON snapshot + its images/ directory, or local PHP arrays for
 * permissions/menus) - no HTTP client is ever touched, no network
 * access is required or attempted.
 */
class SeedPhoneKinboCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'phonekinbo:seed
        {--fresh : Also drop and recreate every table first (migrate:fresh) - DESTRUCTIVE, asks for confirmation in production unless --force is also given}
        {--force : Skip the production confirmation prompt (passed through to migrate:fresh/db:seed)}';

    protected $description = 'Rebuild the complete Phone Kinbo catalogue (brands, phones, variants, specs, prices, availability, reviews, images) from the committed offline seed snapshot, entirely offline.';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            if (! $this->confirmToProceed('This will drop and recreate every table, deleting all current data.')) {
                return self::FAILURE;
            }

            $this->info('Dropping and recreating every table (migrate:fresh)...');
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $this->info('Seeding admin user, permissions, menus, and the full phone catalogue (db:seed)...');
        $this->call('db:seed', ['--force' => true]);

        $this->newLine();
        $this->info('Seed complete. Final catalogue state:');
        $this->report();

        return self::SUCCESS;
    }

    protected function report(): void
    {
        $totalPhones = Phone::count();
        $activePhones = Phone::where('is_active', true)->count();
        $verifiedImages = PhoneImage::where('status', 'verified')->count();
        $totalImages = PhoneImage::count();
        $officialPrices = PhonePrice::where('is_active', true)->where('price_type', 'official_bd')->count();
        $unofficialPrices = PhonePrice::where('is_active', true)->where('price_type', 'unofficial_bd')->count();
        $pendingReviews = PhoneDataReview::where('status', 'pending')->count();
        $multiPrimary = PhoneImage::where('is_primary', true)
            ->select('phone_id')
            ->groupBy('phone_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        $dateAnomalies = Phone::whereNotNull('release_date')
            ->whereNotNull('announced_date')
            ->whereColumn('release_date', '<', 'announced_date')
            ->count();

        $this->table(['Metric', 'Count'], [
            ['Brands', Brand::count()],
            ['Phones', $totalPhones],
            ['Active phones', $activePhones],
            ['Variants', PhoneVariant::count()],
            ['Verified images', $verifiedImages],
            ['Total image records', $totalImages],
            ['Active prices (official_bd)', $officialPrices],
            ['Active prices (unofficial_bd)', $unofficialPrices],
            ['Market prices', PhoneMarketPrice::count()],
            ['Availability records', PhoneAvailability::count()],
            ['Data reviews (total)', PhoneDataReview::count()],
            ['Pending reviews', $pendingReviews],
            ['Multi-primary image conflicts', $multiPrimary],
            ['Date anomalies (release < announced)', $dateAnomalies],
        ]);

        if ($pendingReviews > 0) {
            $this->warn("{$pendingReviews} phone(s) have a pending review - the committed snapshot should always be fully resolved. Investigate before treating this seed as production-ready.");
        }

        if ($multiPrimary > 0) {
            $this->warn("{$multiPrimary} phone(s) have more than one primary image - this should never happen from a clean seed.");
        }

        if ($dateAnomalies > 0) {
            $this->warn("{$dateAnomalies} phone(s) have a release_date before their announced_date.");
        }
    }
}
