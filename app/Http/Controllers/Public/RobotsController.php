<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Seo\SeoMeta;
use App\Support\AdminPaths;
use Illuminate\Http\Response;

/**
 * Dynamic (not a static public/robots.txt) so the Sitemap directive can
 * use the real config('seo.base_url') instead of a domain hardcoded at
 * deploy time - see routes/web.php for why this has to be a route rather
 * than a static file (a static public/robots.txt would be served by the
 * webserver before Laravel's router ever runs).
 */
class RobotsController extends Controller
{
    public function index(): Response
    {
        $disallow = array_map(fn (string $prefix) => '/'.$prefix, AdminPaths::prefixes());

        $lines = ['User-agent: *'];
        foreach ($disallow as $path) {
            $lines[] = "Disallow: {$path}";
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.SeoMeta::absoluteUrl('/sitemap.xml');

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
