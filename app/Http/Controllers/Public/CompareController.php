<?php

namespace App\Http\Controllers\Public;

use App\Enums\PhoneRegionEnum;
use App\Enums\PriceTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Phone;
use App\Services\Presentation\PhonePerformanceProfile;
use App\Services\Presentation\PhoneVariantRegionSelector;
use App\Services\Seo\SeoMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Foundation only: pick up to 4 phones (by slug, so the URL is
 * shareable) and see their specs side by side. Deliberately no
 * saved/named comparisons, no cross-session state yet.
 */
class CompareController extends Controller
{
    protected const MAX_PHONES = 4;

    public function index(Request $request): Response
    {
        $slugs = array_slice(array_filter(explode(',', (string) $request->query('phones', ''))), 0, self::MAX_PHONES);

        $selected = Phone::query()
            ->publiclyVisible()
            ->whereIn('slug', $slugs)
            ->with(['brand:id,name,slug', 'spec', 'primaryImage', 'variants.marketPrices'])
            ->get()
            ->sortBy(fn (Phone $phone) => array_search($phone->slug, $slugs, true))
            ->values()
            ->map(function (Phone $phone) {
                // Same "which variant/price would we actually show" rule as
                // the phone detail page and the recommendation engine:
                // Global preferred over Chinese, then the variant with the
                // cheapest current market price within that region - see
                // PhoneVariantRegionSelector. Never silently compares a
                // Chinese variant of one phone against a Global variant of
                // another without saying so - the resolved region is
                // always sent alongside.
                $variant = PhoneVariantRegionSelector::primary($phone->variants);
                $region = $variant ? PhoneRegionEnum::resolve($variant->region) : null;

                $official = $variant?->marketPrices->firstWhere('price_type', PriceTypeEnum::OFFICIAL_BD);
                $unofficial = $variant?->marketPrices->firstWhere('price_type', PriceTypeEnum::UNOFFICIAL_BD);

                return [
                    'slug' => $phone->slug,
                    'name' => $phone->name,
                    'brand' => $phone->brand->name,
                    'image_url' => $phone->primaryImage?->url,
                    'region' => $region?->label(),
                    'market' => [
                        'official' => $official?->toDisplayArray(),
                        'unofficial' => $unofficial?->toDisplayArray(),
                    ],
                    'spec' => $phone->spec,
                    // Same presenter, same PhoneScorer rules as the phone
                    // detail page (App\Services\Presentation\PhonePerformanceProfile)
                    // - comparison never scores phones by a second, separate formula.
                    'performanceProfile' => PhonePerformanceProfile::for($phone),
                ];
            });

        return Inertia::render('Public/Compare/Index', [
            'selected' => $selected,
            'maxPhones' => self::MAX_PHONES,
            // Small, deliberately trimmed payload so the client-side picker
            // needs no extra search endpoint for this foundation phase.
            'pickerOptions' => Phone::query()
                ->publiclyVisible()
                ->with('brand:id,name')
                ->orderBy('name')
                ->get(['id', 'brand_id', 'name', 'slug'])
                ->map(fn (Phone $phone) => [
                    'slug' => $phone->slug,
                    'name' => $phone->name,
                    'brand' => $phone->brand->name,
                ]),
            // Always noindex: every combination of ?phones=slug1,slug2 is an
            // arbitrary, effectively infinite user-generated state, not a
            // distinct page worth indexing (see prompt sections 3/14/15).
            // Canonicalizes to the bare /compare shell either way.
            'seo' => SeoMeta::make(
                'Compare phones',
                'Compare specifications and prices side by side for phones available in Bangladesh.',
                '/compare',
            )->noindex()->toArray(),
        ]);
    }
}
