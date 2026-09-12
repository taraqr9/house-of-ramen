<?php

namespace App\Services\SeoLanding;

/**
 * The complete, curated list of indexable SEO landing pages - see the
 * "SEO search-intent landing pages" phase report for the research behind
 * each entry (real competitor content coverage at these exact budget
 * points across MobileDokan/Star Tech/Pickaboo/GSMArena BD/AppleGadgets
 * BD, cross-referenced against what this catalogue can actually support
 * - no fabricated search-volume numbers were used). Adding a page is a
 * one-line change here plus a route regex update (see routes/web.php) -
 * deliberately not a database table, so "only these slugs are routable"
 * stays a property of the code, not something checked at runtime.
 */
class SeoLandingPageRegistry
{
    /**
     * @return list<SeoLandingPage>
     */
    public static function all(): array
    {
        return [
            new SeoLandingPage(
                slug: 'best-phones-under-15000',
                heading: 'Best Phones Under ৳15,000',
                maxBudget: 15000,
            ),
            new SeoLandingPage(
                slug: 'best-phones-under-25000',
                heading: 'Best Phones Under ৳25,000',
                maxBudget: 25000,
            ),
            new SeoLandingPage(
                slug: 'best-phones-under-40000',
                heading: 'Best Phones Under ৳40,000',
                maxBudget: 40000,
            ),
            new SeoLandingPage(
                slug: 'best-gaming-phones-under-25000',
                heading: 'Best Gaming Phones Under ৳25,000',
                maxBudget: 25000,
                category: 'gaming',
                categoryLabel: 'gaming',
            ),
            new SeoLandingPage(
                slug: 'best-camera-phones-under-30000',
                heading: 'Best Camera Phones Under ৳30,000',
                maxBudget: 30000,
                category: 'camera',
                categoryLabel: 'camera',
            ),
        ];
    }

    public static function find(string $slug): ?SeoLandingPage
    {
        return collect(self::all())->firstWhere('slug', $slug);
    }

    /**
     * Regex alternation for the route constraint (see routes/web.php) -
     * built from the registry itself so a page can never be routable
     * without also being registered here.
     */
    public static function routePattern(): string
    {
        return collect(self::all())
            ->map(fn (SeoLandingPage $page) => preg_quote($page->slug, '#'))
            ->implode('|');
    }

    /**
     * @return list<SeoLandingPage>
     */
    public static function relatedTo(SeoLandingPage $current): array
    {
        return collect(self::all())
            ->reject(fn (SeoLandingPage $page) => $page->slug === $current->slug)
            ->values()
            ->all();
    }
}
