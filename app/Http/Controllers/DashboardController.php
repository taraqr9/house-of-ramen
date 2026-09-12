<?php

namespace App\Http\Controllers;

use App\Enums\ConflictStatusEnum;
use App\Enums\ImageStatusEnum;
use App\Enums\PriceTypeEnum;
use App\Enums\ReviewReasonEnum;
use App\Enums\ReviewStatusEnum;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneDataConflict;
use App\Models\PhoneDataReview;
use App\Models\PhoneImage;
use App\Models\PhoneImportRun;
use App\Models\PhoneMarketPrice;
use App\Models\PhonePriceHistory;
use App\Models\PhoneVariant;
use App\Services\Analytics\AnalyticsDashboardService;
use App\Services\Analytics\AnalyticsDateRange;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsDashboardService $analytics,
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()->can('dashboard-view'), 403);

        $page_title = 'Dashboard';

        // Same gate as the phone catalogue data below - a user without
        // phone-view already sees a stripped-down dashboard with none of
        // this app's product data, and visitor analytics is exactly that.
        if (! auth()->user()->can('phone-view')) {
            return view('dashboard', ['page_title' => $page_title, 'hasPhoneCatalogue' => false]);
        }

        $totalPhones = Phone::count();

        // One query for every (phone, price_type, price) row across the whole
        // catalogue - reused below to derive price coverage (overview +
        // pricing sections), the price-range distribution, and the
        // official/unofficial availability split, instead of running a
        // separate whereHas() count per metric.
        $priceRows = PhoneMarketPrice::query()
            ->join('phone_variants', 'phone_variants.id', '=', 'phone_market_prices.phone_variant_id')
            ->select('phone_variants.phone_id', 'phone_market_prices.price_type', 'phone_market_prices.price')
            ->get()
            ->groupBy('phone_id');

        $officialPhoneIds = $priceRows->filter(fn (Collection $rows) => $rows->contains('price_type', PriceTypeEnum::OFFICIAL_BD))->keys();
        $unofficialPhoneIds = $priceRows->filter(fn (Collection $rows) => $rows->contains('price_type', PriceTypeEnum::UNOFFICIAL_BD))->keys();
        $bothPricesCount = $officialPhoneIds->intersect($unofficialPhoneIds)->count();
        $officialPriceCount = $officialPhoneIds->count();
        $unofficialPriceCount = $unofficialPhoneIds->count();
        $anyPriceCount = $priceRows->count();

        $cheapestPriceByPhone = $priceRows->map(fn (Collection $rows) => (float) $rows->min('price'));
        $priceRangeBuckets = $this->priceRangeBuckets($cheapestPriceByPhone);

        // One grouped query for every pending review, reused for the
        // data-quality reason breakdown and the price-outlier stat in the
        // pricing section.
        $pendingByReason = PhoneDataReview::query()
            ->where('status', ReviewStatusEnum::PENDING)
            ->selectRaw('reason, count(*) as aggregate')
            ->groupBy('reason')
            ->pluck('aggregate', 'reason');

        $pendingReviewTotal = $pendingByReason->sum();

        // One grouped query for image status, reused across data quality and
        // image status sections.
        $imagesByStatus = PhoneImage::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $phonesWithAnyImage = Phone::query()->whereHas('images')->count();
        $phonesWithPublishedImage = Phone::query()
            ->whereHas('images', fn ($q) => $q->where('is_primary', true)->where('status', ImageStatusEnum::VERIFIED))
            ->count();

        $lastImport = PhoneImportRun::with('source')->latest('id')->first();

        $recentPriceChanges = PhonePriceHistory::with(['variant.phone', 'store'])
            ->latest('changed_at')
            ->take(5)
            ->get();

        $topBrands = Brand::query()
            ->withCount('phones')
            ->orderByDesc('phones_count')
            ->take(8)
            ->get(['id', 'name', 'slug']);

        return view('dashboard', [
            'page_title' => $page_title,
            'hasPhoneCatalogue' => true,

            // Catalogue overview
            'totalPhones' => $totalPhones,
            'totalBrands' => Brand::count(),
            'totalVariants' => PhoneVariant::count(),
            'phonesWithAnyImage' => $phonesWithAnyImage,
            'phonesMissingImages' => $totalPhones - $phonesWithAnyImage,
            'officialPriceCount' => $officialPriceCount,
            'unofficialPriceCount' => $unofficialPriceCount,
            'bothPricesCount' => $bothPricesCount,

            // Data quality
            'pendingReviewTotal' => $pendingReviewTotal,
            'imageReviewCount' => (int) ($pendingByReason[ReviewReasonEnum::IMAGE_NEEDS_REVIEW->value] ?? 0),
            'duplicateReviewCount' => (int) ($pendingByReason[ReviewReasonEnum::POSSIBLE_DUPLICATE->value] ?? 0),
            'missingDataReviewCount' => (int) ($pendingByReason[ReviewReasonEnum::MISSING_DATA->value] ?? 0),
            'lowConfidenceReviewCount' => (int) ($pendingByReason[ReviewReasonEnum::LOW_CONFIDENCE->value] ?? 0),
            'openConflictsCount' => PhoneDataConflict::where('status', ConflictStatusEnum::OPEN)->count(),

            // Pricing overview
            'onlyOfficialCount' => $officialPriceCount - $bothPricesCount,
            'onlyUnofficialCount' => $unofficialPriceCount - $bothPricesCount,
            'noPriceCount' => $totalPhones - $anyPriceCount,
            'priceUpdatesLast7Days' => PhonePriceHistory::where('changed_at', '>=', now()->subDays(7))->count(),
            'priceOutlierReviewCount' => (int) ($pendingByReason[ReviewReasonEnum::PRICE_OUTLIER->value] ?? 0),
            'recentPriceChanges' => $recentPriceChanges,

            // Import status
            'lastImport' => $lastImport,

            // Image status
            'imagesNeedingReview' => (int) ($imagesByStatus[ImageStatusEnum::NEEDS_REVIEW->value] ?? 0),
            'imagesRejected' => (int) ($imagesByStatus[ImageStatusEnum::REJECTED->value] ?? 0),
            'phonesWithPublishedImage' => $phonesWithPublishedImage,

            // Catalogue distribution
            'topBrands' => $topBrands,
            'priceRangeBuckets' => $priceRangeBuckets,

            // A small GA4 summary card only (App\Services\Analytics\*) -
            // the full breakdowns live on the dedicated /admin/analytics
            // page (App\Http\Controllers\AnalyticsController) so this
            // dashboard stays focused on catalogue/admin work. Fixed to the
            // default range rather than a page-level selector - see
            // resources/views/dashboard.blade.php's "View Analytics ->" link.
            'analyticsSummary' => $this->analytics->summary(AnalyticsDateRange::fromKey(null)),
        ]);
    }

    /**
     * Bucket each phone's cheapest current market price into the same
     * budget breakpoints the public site uses (config('phone_kinbo.price_brackets')),
     * but as exclusive bands (each phone counted once) rather than the public
     * site's cumulative "under X" framing - more useful for a distribution view.
     *
     * @return list<array{label: string, count: int}>
     */
    private function priceRangeBuckets(Collection $cheapestPriceByPhone): array
    {
        $buckets = [];
        $lowerBound = 0;

        foreach (config('phone_kinbo.price_brackets') as $bracket) {
            $upperBound = $bracket['max_budget'];

            $count = $upperBound === null
                ? $cheapestPriceByPhone->filter(fn ($price) => $price > $lowerBound)->count()
                : $cheapestPriceByPhone->filter(fn ($price) => $price > $lowerBound && $price <= $upperBound)->count();

            $label = match (true) {
                $upperBound === null => '৳'.number_format($lowerBound).'+',
                $lowerBound === 0 => 'Under ৳'.number_format($upperBound),
                default => '৳'.number_format($lowerBound).' – ৳'.number_format($upperBound),
            };

            $buckets[] = ['label' => $label, 'count' => $count];
            $lowerBound = $upperBound ?? $lowerBound;
        }

        return $buckets;
    }
}
