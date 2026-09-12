<?php

namespace App\Http\Controllers\Public;

use App\Enums\PriceTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhonePrice;
use App\Services\Seo\SeoMeta;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The brand landing pages (/phones/brand/{slug}) called for in the SEO
 * foundation work - real, useful aggregate information about a brand's
 * catalogue (count, price range, official/unofficial split) plus links
 * into every phone, rather than a generic templated paragraph.
 */
class BrandController extends Controller
{
    public function show(Brand $brand): Response
    {
        abort_unless($brand->is_active, 404);

        $phones = Phone::query()
            ->publiclyVisible()
            ->where('brand_id', $brand->id)
            ->with('primaryImage')
            ->withDisplayMarketPrice()
            ->orderByDesc('release_date')
            ->get();

        abort_if($phones->isEmpty(), 404);

        // Region-scoped the same way as PhoneController::index() (Global
        // preferred, Chinese only as a fallback) - see Phone::displayMarketPrice().
        $prices = $phones->map(fn (Phone $phone) => $phone->displayMarketPrice())->filter();

        // Restricted to the same publicly-visible phone IDs already
        // resolved above, so this count can never include a phone that
        // isn't actually shown in the list below.
        $phoneIds = $phones->pluck('id');

        $officialCount = PhonePrice::query()
            ->join('phone_variants', 'phone_variants.id', '=', 'phone_prices.phone_variant_id')
            ->whereIn('phone_variants.phone_id', $phoneIds)
            ->where('phone_prices.is_active', true)
            ->where('phone_prices.price_type', PriceTypeEnum::OFFICIAL_BD)
            ->distinct('phone_variants.phone_id')
            ->count('phone_variants.phone_id');

        $unofficialCount = PhonePrice::query()
            ->join('phone_variants', 'phone_variants.id', '=', 'phone_prices.phone_variant_id')
            ->whereIn('phone_variants.phone_id', $phoneIds)
            ->where('phone_prices.is_active', true)
            ->where('phone_prices.price_type', PriceTypeEnum::UNOFFICIAL_BD)
            ->distinct('phone_variants.phone_id')
            ->count('phone_variants.phone_id');

        $title = "{$brand->name} Phones Price in Bangladesh (".now()->format('Y').')';
        $description = sprintf(
            '%d %s phones tracked in Bangladesh, from ৳%s to ৳%s. Compare official and unofficial prices and specs on Phone Kinbo.',
            $phones->count(),
            $brand->name,
            $prices->isNotEmpty() ? number_format($prices->min()) : '—',
            $prices->isNotEmpty() ? number_format($prices->max()) : '—',
        );

        return Inertia::render('Public/Phones/Brand', [
            'brand' => [
                'name' => $brand->name,
                'slug' => $brand->slug,
                'country' => $brand->country,
            ],
            'stats' => [
                'phone_count' => $phones->count(),
                'min_price' => $prices->isNotEmpty() ? (float) $prices->min() : null,
                'max_price' => $prices->isNotEmpty() ? (float) $prices->max() : null,
                'official_count' => $officialCount,
                'unofficial_count' => $unofficialCount,
            ],
            'phones' => $phones->map(fn (Phone $phone) => [
                'slug' => $phone->slug,
                'name' => $phone->name,
                'price' => $phone->displayMarketPrice(),
                'image_url' => $phone->primaryImage?->url,
            ])->values(),
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Phones', 'href' => '/phones'],
                ['label' => $brand->name, 'href' => null],
            ],
            'seo' => SeoMeta::make($title, $description, "/phones/brand/{$brand->slug}")->toArray(),
        ]);
    }
}
