<?php

namespace App\Http\Controllers\Public;

use App\Enums\AvailabilityStatusEnum;
use App\Enums\PhoneRegionEnum;
use App\Enums\PriceTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneVariant;
use App\Services\Presentation\PhoneHighlights;
use App\Services\Presentation\PhonePerformanceProfile;
use App\Services\Presentation\PhoneVariantRegionSelector;
use App\Services\Seo\SeoMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PhoneController extends Controller
{
    protected const SEARCH_RESULT_LIMIT = 8;

    /**
     * Header/nav autocomplete - a lightweight JSON endpoint (not an
     * Inertia page) so the client can poll it per keystroke without
     * shipping the whole catalogue to the browser. Matches by phone name,
     * model number, or brand name; every search word must match at least
     * one of those three fields (word-by-word AND, field-by-field OR)
     * rather than requiring one field to contain the whole phrase, so a
     * compound query like "Samsung A55" finds "Samsung"+"Galaxy A55"
     * even though neither field alone contains the full string. Reuses
     * the exact same publiclyVisible()/marketPrices building blocks as
     * the main catalogue query above, so results and their prices are
     * never a second, drifting source of truth.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $words = array_values(array_filter(preg_split('/\s+/', trim($validated['q'])) ?: []));

        $query = Phone::query()
            ->publiclyVisible()
            ->with(['brand:id,name,slug', 'primaryImage'])
            ->withDisplayMarketPrice();

        foreach ($words as $word) {
            $query->where(function ($group) use ($word) {
                $group->where('name', 'like', "%{$word}%")
                    ->orWhere('model_number', 'like', "%{$word}%")
                    ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', "%{$word}%"));
            });
        }

        $phones = $query
            // Prefix matches on the phone's own name first (searching
            // "iphone 15" should surface "iPhone 15" before an unrelated
            // "iPhone 15 Pro Max" accessory-style match further down),
            // then alphabetical.
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$validated['q'].'%'])
            ->orderBy('name')
            ->limit(self::SEARCH_RESULT_LIMIT)
            ->get()
            ->map(fn (Phone $phone) => [
                'slug' => $phone->slug,
                'name' => $phone->name,
                'brand' => $phone->brand->name,
                'price' => $phone->displayMarketPrice(),
                'image_url' => $phone->primaryImage?->url,
            ]);

        return response()->json(['results' => $phones]);
    }

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'brand' => ['nullable', 'string', 'exists:brands,slug'],
            'max_budget' => ['nullable', 'integer', 'min:1000'],
            'sort' => ['nullable', 'in:price_asc,price_desc,newest'],
        ]);

        // "Price" on the browse card is the cheapest current MARKET price
        // (official or unofficial) - the outlier-resistant aggregate, never
        // a raw single-retailer row. See App\Services\PhoneImport\PriceAggregator.
        // Region-scoped via withDisplayMarketPrice(): Global preferred,
        // Chinese only as a fallback when the phone has no Global price at
        // all (see Phone::displayMarketPrice() and App\Enums\PhoneRegionEnum) -
        // one catalogue card per phone, never a second card for its
        // Chinese-market sibling.
        $query = Phone::query()
            ->publiclyVisible()
            ->with(['brand:id,name,slug', 'primaryImage'])
            ->withDisplayMarketPrice();

        if (! empty($validated['brand'])) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $validated['brand']));
        }

        if (! empty($validated['max_budget'])) {
            $query->whereHas('marketPrices', fn ($q) => $q->where('price', '<=', $validated['max_budget']));
        }

        // A phone with no current market price has nothing to rank by -
        // MySQL treats NULL as the lowest possible value in ASC order, so
        // without this exclusion a priceless phone would misleadingly sort
        // to the very top of "Price: low to high" as if it were the
        // cheapest option in the catalogue. It still appears under every
        // other sort (default "newest", brand, etc.) with an honest "Price
        // unavailable" label - only a price-based ordering excludes it.
        // The COALESCE mirrors displayMarketPrice()'s "Global, else
        // Chinese" fallback so the sort order matches what's actually shown.
        match ($validated['sort'] ?? 'newest') {
            'price_asc' => $query->whereHas('marketPrices')->orderByRaw('COALESCE(global_market_prices_min_price, chinese_market_prices_min_price) ASC'),
            'price_desc' => $query->whereHas('marketPrices')->orderByRaw('COALESCE(global_market_prices_min_price, chinese_market_prices_min_price) DESC'),
            default => $query->orderByDesc('release_date'),
        };

        $phones = $query->paginate(24)->withQueryString();

        // Only for the 24 phones on this page (never the full, unpaginated
        // catalogue) - cheap enough to eager-load fully so the card can
        // show which price type (official/unofficial) and region actually
        // produced the displayed number, on top of the SQL-level aggregate
        // above that already picked the right amount for sorting.
        $phones->getCollection()->load([
            'variants' => fn ($q) => $q->where('is_active', true),
            'variants.marketPrices',
        ]);

        // Only pagination keeps a /phones URL indexable - it's genuinely
        // different content per page. brand/max_budget/sort reorder or
        // subset the same underlying catalogue (the brand slice already has
        // its own clean, indexable URL at /phones/brand/{slug}), so those
        // combinations canonicalize back to the bare listing and are kept
        // out of the index to avoid an explosion of near-duplicate pages
        // (see prompt section 14).
        $hasNonPaginationFilters = $request->filled('brand') || $request->filled('max_budget') || $request->filled('sort');
        $page = (int) $request->query('page', 1);
        $canonicalPath = '/phones'.($page > 1 ? '?page='.$page : '');

        $seo = SeoMeta::make(
            'Browse phones in Bangladesh',
            'Browse every phone Phone Kinbo tracks in Bangladesh, with current official and unofficial market prices.',
            $canonicalPath,
        );

        if ($hasNonPaginationFilters) {
            $seo->noindex();
        }

        return Inertia::render('Public/Phones/Index', [
            'phones' => $phones->through(function (Phone $phone) {
                $display = PhoneVariantRegionSelector::displayPrice($phone->variants);
                $marketPrice = $display['market_price'] ?? null;

                return [
                    'id' => $phone->id,
                    'slug' => $phone->slug,
                    'name' => $phone->name,
                    'brand' => $phone->brand->name,
                    'price' => $marketPrice ? (float) $marketPrice->price : null,
                    'is_official' => $marketPrice ? $marketPrice->price_type === PriceTypeEnum::OFFICIAL_BD : null,
                    'region' => $display ? $display['region']->label() : null,
                    'image_url' => $phone->primaryImage?->url,
                ];
            }),
            'brands' => Brand::query()
                ->where('is_active', true)
                ->whereHas('phones', fn ($q) => $q->publiclyVisible())
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'priceBrackets' => config('phone_kinbo.price_brackets'),
            'filters' => [
                'brand' => $validated['brand'] ?? null,
                // Cast to int: query-string values arrive as strings, but
                // config('phone_kinbo.price_brackets')'s max_budget values
                // are PHP ints - the frontend's active-bracket highlight
                // does a strict match against that config, so a string vs.
                // int mismatch here silently breaks the highlight even
                // though filtering itself works fine either way.
                'max_budget' => isset($validated['max_budget']) ? (int) $validated['max_budget'] : null,
                'sort' => $validated['sort'] ?? 'newest',
            ],
            'seo' => $seo->toArray(),
        ]);
    }

    public function show(Phone $phone): Response
    {
        // Same rule as Phone::scopePubliclyVisible() - active only. No
        // verified image is required: the frontend PhoneImage component
        // already falls back to an honest placeholder when image_url is
        // null (see resources/js/Components/Public/PhoneImage.vue).
        abort_unless($phone->is_active, 404);

        $phone->load([
            'brand:id,name,slug',
            'spec',
            'primaryImage',
            'variants' => fn ($q) => $q->where('is_active', true)->orderBy('storage_gb'),
            'variants.marketPrices',
            'variants.prices' => fn ($q) => $q->where('is_active', true),
            'variants.prices.store:id,name',
            'variants.availabilities' => fn ($q) => $q->latest('collected_at')->limit(1),
        ]);

        // The headline price/market block: Global preferred over Chinese,
        // then cheapest current market price (official or unofficial)
        // within that region - see PhoneVariantRegionSelector.
        $primaryVariant = PhoneVariantRegionSelector::primary($phone->variants);
        $primaryRegion = $primaryVariant ? PhoneRegionEnum::resolve($primaryVariant->region) : null;

        $officialMarket = $primaryVariant?->marketPrices->firstWhere('price_type', PriceTypeEnum::OFFICIAL_BD);
        $unofficialMarket = $primaryVariant?->marketPrices->firstWhere('price_type', PriceTypeEnum::UNOFFICIAL_BD);

        // Every variant grouped by user-facing region (Global/Chinese) for
        // the detailed RAM/storage/price table - only regions the phone
        // actually has a variant for, Global first. A single-region phone
        // (527 of the current 530) yields exactly one group, so the
        // frontend renders no region selector at all for it.
        $variantRegionGroups = PhoneVariantRegionSelector::grouped($phone->variants);

        // Real availability, not an assumed "in stock" - Product/Offer JSON-LD
        // must reflect the actual most-recently-collected status (see prompt
        // section 8: "Do not generate invalid or misleading schema").
        $availabilityStatus = $primaryVariant?->availabilities->first()?->status;
        $schemaAvailability = match ($availabilityStatus) {
            AvailabilityStatusEnum::IN_STOCK => 'https://schema.org/InStock',
            AvailabilityStatusEnum::OUT_OF_STOCK => 'https://schema.org/OutOfStock',
            AvailabilityStatusEnum::PREORDER => 'https://schema.org/PreOrder',
            AvailabilityStatusEnum::DISCONTINUED => 'https://schema.org/Discontinued',
            default => null,
        };

        $related = Phone::query()
            ->publiclyVisible()
            ->where('brand_id', $phone->brand_id)
            ->where('id', '!=', $phone->id)
            ->with(['brand:id,name,slug', 'primaryImage'])
            ->withDisplayMarketPrice()
            ->limit(4)
            ->get()
            ->map(fn (Phone $related) => [
                'slug' => $related->slug,
                'name' => $related->name,
                'brand' => $related->brand->name,
                'price' => $related->displayMarketPrice(),
                'image_url' => $related->primaryImage?->url,
            ]);

        $cheapestPrice = collect([$officialMarket, $unofficialMarket])->filter()->min('price');

        $title = $cheapestPrice
            ? "{$phone->name} Price in Bangladesh (৳".number_format($cheapestPrice).') & Full Specs'
            : "{$phone->name} Price in Bangladesh & Full Specs";

        $description = trim(sprintf(
            '%s %s: %s. %sSee official and unofficial Bangladesh prices, specs, and availability on Phone Kinbo.',
            $phone->brand->name,
            $phone->name,
            $phone->spec?->processor ?? 'full specifications',
            $cheapestPrice ? 'From ৳'.number_format($cheapestPrice).'. ' : '',
        ));

        return Inertia::render('Public/Phones/Show', [
            'phone' => [
                'id' => $phone->id,
                'slug' => $phone->slug,
                'name' => $phone->name,
                'brand' => $phone->brand->name,
                'brand_slug' => $phone->brand->slug,
                'image_url' => $phone->primaryImage?->url,
                'image_attribution' => $phone->primaryImage?->attribution,
                'summary' => $phone->summary,
                'release_date' => $phone->release_date?->toDateString(),
                'status' => $phone->status->label(),
                'schema_availability' => $schemaAvailability,
                'market' => [
                    'official' => $officialMarket?->toDisplayArray(),
                    'unofficial' => $unofficialMarket?->toDisplayArray(),
                ],
                'primary_region' => $primaryRegion?->label(),
                // Grouped by user-facing region (Global/Chinese) rather
                // than a flat list - see PhoneVariantRegionSelector::grouped().
                // Each variant's config_label is RAM/storage/color only
                // (no region - that's already the group heading).
                'variant_regions' => $variantRegionGroups->map(fn (Collection $variants, string $regionKey) => [
                    'region' => PhoneRegionEnum::from($regionKey)->label(),
                    'variants' => $variants->map(fn (PhoneVariant $variant) => [
                        'id' => $variant->id,
                        'config_label' => collect([
                            $variant->ram_gb ? "{$variant->ram_gb}GB" : null,
                            $variant->storage_gb ? "{$variant->storage_gb}GB" : null,
                            $variant->color,
                        ])->filter()->implode(' + ') ?: 'Standard',
                        'is_official_bd' => $variant->is_official_bd,
                        'availability' => $variant->availabilities->first()?->status->label(),
                        'prices' => $variant->prices->map(fn ($price) => [
                            'type' => $price->price_type->label(),
                            'is_official' => $price->price_type === PriceTypeEnum::OFFICIAL_BD,
                            'amount' => (float) $price->amount,
                            'store' => $price->store?->name,
                            'warranty_type' => $price->warranty_type,
                        ]),
                    ])->values(),
                ])->values(),
                'spec' => $phone->spec,
            ],
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Phones', 'href' => '/phones'],
                ['label' => $phone->brand->name, 'href' => "/phones/brand/{$phone->brand->slug}"],
                ['label' => $phone->name, 'href' => null],
            ],
            'seo' => SeoMeta::make($title, $description, "/phones/{$phone->slug}")
                ->ogImage($phone->primaryImage?->url)
                ->ogType('product')
                ->toArray(),
            'highlights' => PhoneHighlights::for($phone),
            'performanceProfile' => PhonePerformanceProfile::for($phone),
            'related' => $related,
        ]);
    }
}
