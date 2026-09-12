<?php

namespace App\Console\Commands;

use App\Enums\PriceTypeEnum;
use App\Enums\RetailerMatchStatusEnum;
use App\Models\Phone;
use App\Models\PhoneMarketPrice;
use App\Models\PhonePrice;
use App\Models\PhoneRetailerMatchAttempt;
use App\Models\PhoneVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only catalogue-wide pricing coverage report - never writes
 * anything. Answers exactly the questions that matter after a
 * discovery pass: how much of the catalogue has a REAL, FRESH price
 * (not stale seed data left over from before real retailer sources
 * existed), and where the remaining gaps are.
 */
class PhonePricingAuditCommand extends Command
{
    protected $signature = 'phones:pricing-audit {--brand= : Limit the weakest-coverage breakdown to one brand slug}';

    protected $description = 'Report catalogue-wide price coverage: fresh vs stale, matched vs unmatched, outliers rejected, failures.';

    public function handle(): int
    {
        $activePhones = Phone::where('is_active', true);
        $totalPhones = $activePhones->count();
        $totalVariants = PhoneVariant::where('is_active', true)->count();

        $freshCutoff = now()->subDays(config('phone_pricing.stale_after_days'));

        // A phone "has a fresh X price" when at least one of its active
        // variants has a phone_market_prices row for that price_type
        // whose calculated_at is within the freshness window - i.e. it
        // was actually derived from currently-fresh observations, not
        // left over from a calculation days/weeks ago that has since
        // gone stale (see PriceAggregator::recalculate()'s own freshness
        // gate, which is what calculated_at reflects).
        $phonesWithFreshOfficial = $this->phonesWithFreshMarketPrice(PriceTypeEnum::OFFICIAL_BD, $freshCutoff);
        $phonesWithFreshUnofficial = $this->phonesWithFreshMarketPrice(PriceTypeEnum::UNOFFICIAL_BD, $freshCutoff);
        $phonesWithBoth = $phonesWithFreshOfficial->intersect($phonesWithFreshUnofficial);

        // NOT ->union() - Collection::union() merges by array KEY (like
        // the + operator), not by value, so it is not a set union at all
        // here and previously produced a wrong (inflated) "neither"
        // count. concat()->unique() is the real set union of phone ids.
        $phonesWithEither = $phonesWithFreshOfficial->concat($phonesWithFreshUnofficial)->unique();
        $phonesWithNeither = $totalPhones - $phonesWithEither->count();

        $observationCount = PhonePrice::where('is_active', true)->count();
        $outlierRejections = PhoneMarketPrice::sum('outlier_count');

        $attemptCounts = PhoneRetailerMatchAttempt::selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status->value => $row->c]);

        $this->info('=== Phone Kinbo Pricing Coverage Audit ===');
        $this->line('Generated: '.now()->toDateTimeString());
        $this->newLine();

        $this->table(['Metric', 'Count'], [
            ['Total active phones', $totalPhones],
            ['Total active variants', $totalVariants],
            ['Phones with fresh official price', $phonesWithFreshOfficial->count()],
            ['Phones with fresh unofficial price', $phonesWithFreshUnofficial->count()],
            ['Phones with both', $phonesWithBoth->count()],
            ['Phones with no fresh price at all', $phonesWithNeither],
            ['Active retailer price observations', $observationCount],
            ['Outlier observations rejected (cumulative)', (int) $outlierRejections],
            ['Retailer match attempts: matched', (int) ($attemptCounts[RetailerMatchStatusEnum::MATCHED->value] ?? 0)],
            ['Retailer match attempts: no candidate found', (int) ($attemptCounts[RetailerMatchStatusEnum::NO_CANDIDATE->value] ?? 0)],
            ['Retailer match attempts: variant ambiguous', (int) ($attemptCounts[RetailerMatchStatusEnum::VARIANT_AMBIGUOUS->value] ?? 0)],
            ['Retailer match attempts: name mismatch', (int) ($attemptCounts[RetailerMatchStatusEnum::NAME_MISMATCH->value] ?? 0)],
            ['Retailer match attempts: fetch failed', (int) ($attemptCounts[RetailerMatchStatusEnum::FETCH_FAILED->value] ?? 0)],
        ]);

        $this->newLine();
        $this->info('Coverage by retailer:');
        $bySource = PhoneRetailerMatchAttempt::selectRaw('source_key, status, count(*) as c')
            ->groupBy('source_key', 'status')
            ->get()
            ->groupBy('source_key');

        $rows = [];
        foreach ($bySource as $sourceKey => $rowsForSource) {
            $byStatus = $rowsForSource->mapWithKeys(fn ($row) => [$row->status->value => $row->c]);
            $rows[] = [
                $sourceKey,
                $byStatus[RetailerMatchStatusEnum::MATCHED->value] ?? 0,
                $byStatus[RetailerMatchStatusEnum::NO_CANDIDATE->value] ?? 0,
                $byStatus[RetailerMatchStatusEnum::VARIANT_AMBIGUOUS->value] ?? 0,
                $byStatus[RetailerMatchStatusEnum::NAME_MISMATCH->value] ?? 0,
                $byStatus[RetailerMatchStatusEnum::FETCH_FAILED->value] ?? 0,
            ];
        }
        $this->table(['Retailer', 'Matched', 'No Candidate', 'Ambiguous', 'Name Mismatch', 'Fetch Failed'], $rows);

        $this->newLine();
        $this->info('Weakest price coverage by brand (fresh official+unofficial phones / active phones):');
        $this->weakestBrands($phonesWithFreshOfficial, $phonesWithFreshUnofficial);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, int> phone ids
     */
    protected function phonesWithFreshMarketPrice(PriceTypeEnum $type, Carbon $freshCutoff): Collection
    {
        return Phone::where('is_active', true)
            ->whereHas('variants', fn ($q) => $q->where('is_active', true)
                ->whereHas('marketPrices', fn ($q2) => $q2->where('price_type', $type->value)->where('calculated_at', '>=', $freshCutoff)))
            ->pluck('id');
    }

    protected function weakestBrands(Collection $freshOfficial, Collection $freshUnofficial): void
    {
        $freshEither = $freshOfficial->merge($freshUnofficial)->unique();

        $query = Phone::where('is_active', true)->with('brand');

        if ($brandSlug = $this->option('brand')) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug));
        }

        $byBrand = $query->get()->groupBy(fn ($phone) => $phone->brand->name);

        $rows = $byBrand->map(function ($phones, $brandName) use ($freshEither) {
            $total = $phones->count();
            $withPrice = $phones->pluck('id')->intersect($freshEither)->count();

            return [$brandName, $withPrice, $total, $total > 0 ? round($withPrice / $total * 100, 1).'%' : '-'];
        })->sortBy(fn ($row) => (float) $row[3])->values()->take(10);

        $this->table(['Brand', 'With fresh price', 'Total active phones', 'Coverage'], $rows->all());
    }
}
