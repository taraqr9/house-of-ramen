<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Seo\SeoMeta;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * The full set of public pages - small and static enough (Home, Menu,
 * Gallery, About, Contact) that a sitemap index or per-item lastmod
 * tracking would be pure overhead at this site's size.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn () => $this->buildXml());

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    protected function buildXml(): string
    {
        $urls = [
            ['loc' => SeoMeta::absoluteUrl('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => SeoMeta::absoluteUrl('/menu'), 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => SeoMeta::absoluteUrl('/gallery'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => SeoMeta::absoluteUrl('/about'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => SeoMeta::absoluteUrl('/contact'), 'changefreq' => 'monthly', 'priority' => '0.6'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($url['loc'])."</loc>\n";
            $xml .= '    <changefreq>'.$url['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$url['priority']."</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
