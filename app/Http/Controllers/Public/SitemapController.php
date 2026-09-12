<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Phone;
use App\Services\Recommendation\PhoneRecommendationEngine;
use App\Services\Seo\SeoMeta;
use App\Services\SeoLanding\SeoLandingPageRegistry;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Only genuinely indexable URLs - homepage, brand pages, and phone detail
 * pages. Deliberately excludes /compare, /find-my-phone/results, any
 * filtered /phones?... variant, and anything noindex'd elsewhere in the
 * app (see prompt section 10). Plain string-built XML - at catalogue
 * scale (hundreds of phones, tens of brands) a templating layer or a
 * sitemap index (needed only past ~50k URLs) would be pure overhead.
 */
class SitemapController extends Controller
{
    public function index(PhoneRecommendationEngine $engine): Response
    {
        // Building this runs the real recommendation engine once per
        // registered landing page on top of the brand/phone listing
        // queries (~30 queries total) - real cost, but the underlying
        // data only meaningfully changes once a day (the nightly import)
        // or on an infrequent admin edit, so an hour of staleness is
        // free correctness the sitemap doesn't need finer than. A single
        // cache call, not a caching layer.
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn () => $this->buildXml($engine));

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    protected function buildXml(PhoneRecommendationEngine $engine): string
    {
        $urls = [
            ['loc' => SeoMeta::absoluteUrl('/'), 'lastmod' => null, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => SeoMeta::absoluteUrl('/phones'), 'lastmod' => null, 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => SeoMeta::absoluteUrl('/about'), 'lastmod' => null, 'changefreq' => 'monthly', 'priority' => '0.5'],
        ];

        // Same rule SeoLandingPagePresenter uses to noindex a page - a
        // budget/category page with nothing to show right now is left out
        // of the sitemap entirely rather than submitted to Google empty.
        foreach (SeoLandingPageRegistry::all() as $page) {
            if (count($engine->rank($page->criteria())) === 0) {
                continue;
            }

            $urls[] = [
                'loc' => SeoMeta::absoluteUrl("/{$page->slug}"),
                'lastmod' => null,
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];
        }

        Brand::query()
            ->where('is_active', true)
            ->whereHas('phones', fn ($q) => $q->publiclyVisible())
            ->get(['slug', 'updated_at'])
            ->each(function (Brand $brand) use (&$urls) {
                $urls[] = [
                    'loc' => SeoMeta::absoluteUrl("/phones/brand/{$brand->slug}"),
                    'lastmod' => $brand->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            });

        Phone::query()
            ->publiclyVisible()
            ->get(['slug', 'updated_at', 'last_verified_at'])
            ->each(function (Phone $phone) use (&$urls) {
                $urls[] = [
                    'loc' => SeoMeta::absoluteUrl("/phones/{$phone->slug}"),
                    'lastmod' => ($phone->last_verified_at ?? $phone->updated_at)?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($url['loc'])."</loc>\n";
            if ($url['lastmod']) {
                $xml .= '    <lastmod>'.$url['lastmod']."</lastmod>\n";
            }
            $xml .= '    <changefreq>'.$url['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$url['priority']."</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
