<?php

namespace App\Services\PhoneImage\Sources;

use App\Models\Phone;
use App\Services\PhoneImage\Contracts\PhoneImageSourceProvider;
use App\Services\PhoneImage\Support\MatchesPhoneImageText;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Finds a real product photo for a phone on Wikimedia Commons - openly
 * licensed media where every file carries machine-readable license and
 * author metadata, unlike a generic image search. A candidate is never
 * trusted blindly: title/category text is matched against the phone's
 * brand+model (see MatchesPhoneImageText), obvious non-photos (box art,
 * screenshots, logos) are filtered out, and anything without a
 * resolvable license is capped below the auto-publish confidence
 * threshold so it lands in review instead of going live silently.
 */
class WikimediaCommonsImageSource implements PhoneImageSourceProvider
{
    use MatchesPhoneImageText;

    protected const API_URL = 'https://commons.wikimedia.org/w/api.php';

    public function __construct(
        protected readonly string $sourceKey,
        protected readonly array $config = [],
    ) {}

    public function key(): string
    {
        return $this->sourceKey;
    }

    public function search(Phone $phone): array
    {
        $query = $this->buildSearchQuery($phone);

        $tokens = $this->tokenize($query);

        if (empty($tokens)) {
            return [];
        }

        $response = $this->apiSearch($query);

        if ($response === null) {
            return [];
        }

        $pages = data_get($response, 'query.pages', []);

        $phrase = trim(preg_replace('/\s+/', ' ', strtolower($query)));

        $candidates = [];

        foreach ($pages as $page) {
            $candidate = $this->scoreCandidate($page, $tokens, $phrase);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        usort($candidates, function (array $a, array $b) {
            if ($a['confidence'] !== $b['confidence']) {
                return $b['confidence'] <=> $a['confidence'];
            }

            return ($b['width'] * $b['height']) <=> ($a['width'] * $a['height']);
        });

        return array_values($candidates);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function apiSearch(string $query): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                ->timeout(15)
                ->retry(2, 500)
                ->get(self::API_URL, [
                    'action' => 'query',
                    'generator' => 'search',
                    'gsrsearch' => $query.' filetype:bitmap',
                    'gsrnamespace' => 6,
                    'gsrlimit' => 10,
                    'prop' => 'imageinfo',
                    'iiprop' => 'url|extmetadata|size|mime',
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            Log::channel('custom_error')->warning('Wikimedia Commons image search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  list<string>  $tokens
     * @return array<string, mixed>|null
     */
    protected function scoreCandidate(array $page, array $tokens, string $phrase): ?array
    {
        $info = data_get($page, 'imageinfo.0');

        if (! $info) {
            return null;
        }

        $title = (string) ($page['title'] ?? '');
        $titleLower = $this->normalizeText(str_replace('File:', '', $title));
        $width = (int) ($info['width'] ?? 0);
        $height = (int) ($info['height'] ?? 0);

        if ($width < 300 || $height < 300) {
            return null;
        }

        if ($this->containsRejectedWord($titleLower)) {
            return null;
        }

        $categories = $this->normalizeText($this->stripCameraExifCategories(
            (string) data_get($info, 'extmetadata.Categories.value', '')
        ));

        $confidence = $this->scoreTextMatch($titleLower, $categories, $phrase, $tokens);

        $license = data_get($info, 'extmetadata.LicenseShortName.value')
            ?? data_get($info, 'extmetadata.UsageTerms.value');

        if (! $license) {
            // No resolvable license metadata - never let this cross the
            // auto-publish threshold, whatever the text match looked like.
            $confidence = min($confidence, 40);
        }

        $artist = $this->cleanHtml((string) data_get($info, 'extmetadata.Artist.value', ''));
        $attribution = $artist !== ''
            ? "{$artist}".($license ? ", {$license}" : '').', via Wikimedia Commons'
            : trim(($license ? "{$license}, " : '').'via Wikimedia Commons');

        return [
            'url' => $info['url'] ?? null,
            'source_url' => $info['descriptionurl'] ?? null,
            'title' => $title,
            'width' => $width,
            'height' => $height,
            'mime_type' => $info['mime'] ?? null,
            'license' => $license,
            'attribution' => $attribution,
            'confidence' => max(0, min(100, $confidence)),
        ];
    }

    /**
     * Wikimedia auto-adds a "Taken with [Camera/Phone Model]" category
     * from EXIF data to almost any photo shot on that device - a
     * completely different thing from "this photo is OF that device".
     * A dam, a plate of food, or a random street scene "Taken with
     * Samsung Galaxy M05" would otherwise phrase-match at high
     * confidence despite having nothing to do with the phone itself.
     */
    protected function stripCameraExifCategories(string $categories): string
    {
        $kept = array_filter(
            explode('|', $categories),
            fn ($category) => ! preg_match('/^\s*taken with\b/i', $category)
        );

        return implode('|', $kept);
    }

    protected function userAgent(): string
    {
        return $this->config['user_agent'] ?? 'PhoneKinboCatalogueBot/1.0';
    }
}
