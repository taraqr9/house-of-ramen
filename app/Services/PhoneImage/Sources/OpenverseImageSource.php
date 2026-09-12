<?php

namespace App\Services\PhoneImage\Sources;

use App\Models\Phone;
use App\Services\PhoneImage\Contracts\PhoneImageSourceProvider;
use App\Services\PhoneImage\Support\MatchesPhoneImageText;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Openverse (openverse.org) indexes openly-licensed media - Creative
 * Commons and public-domain images - from many providers (Wikimedia
 * Commons, Flickr's CC-licensed subset, museum/cultural collections,
 * etc.), each with explicit, machine-readable license metadata via its
 * public API. Unlike a generic image search, every result here is
 * pre-filtered to media that explicitly permits reuse - Openverse itself
 * only indexes CC-licensed/public-domain works. It complements
 * WikimediaCommonsImageSource rather than replacing it: a recent
 * 2025-2026 phone with no dedicated Commons upload yet may still have a
 * CC-licensed photo from a review outlet, hands-on event, or other
 * provider that Openverse has indexed.
 *
 * Same matching discipline as every other source (see
 * MatchesPhoneImageText) - a result is never trusted just because
 * Openverse found it.
 */
class OpenverseImageSource implements PhoneImageSourceProvider
{
    use MatchesPhoneImageText;

    protected const API_URL = 'https://api.openverse.org/v1/images/';

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

        $results = $this->apiSearch($query);

        if ($results === null) {
            return [];
        }

        $phrase = trim(preg_replace('/\s+/', ' ', strtolower($query)));

        $candidates = [];

        foreach ($results as $result) {
            $candidate = $this->scoreCandidate($result, $tokens, $phrase);

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
     * @return list<array<string, mixed>>|null
     */
    protected function apiSearch(string $query): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                ->timeout(15)
                ->retry(2, 500)
                ->get(self::API_URL, [
                    'q' => $query,
                    // "all-cc" = every Creative Commons license type
                    // (including ND/NC) - a phone product photo is still
                    // legitimately usable editorially even under a
                    // no-derivatives/non-commercial term, and the
                    // attribution/license is always recorded and shown
                    // regardless of which one applies.
                    'license_type' => 'all-cc',
                    'mature' => 'false',
                    'page_size' => 10,
                ]);
        } catch (\Throwable $e) {
            Log::channel('custom_error')->warning('Openverse image search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json('results', []);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $tokens
     * @return array<string, mixed>|null
     */
    protected function scoreCandidate(array $result, array $tokens, string $phrase): ?array
    {
        $url = $result['url'] ?? null;
        $width = (int) ($result['width'] ?? 0);
        $height = (int) ($result['height'] ?? 0);

        if (! $url || $width < 300 || $height < 300) {
            return null;
        }

        $title = (string) ($result['title'] ?? '');
        $titleLower = $this->normalizeText($title);

        if ($this->containsRejectedWord($titleLower)) {
            return null;
        }

        // Openverse has no per-item "category" field the way Commons
        // does - its tags list is the closest analogue, so it fills the
        // same slot in the shared scorer.
        $tags = collect($result['tags'] ?? [])->pluck('name')->implode('|');
        $tagsLower = $this->normalizeText($tags);

        $confidence = $this->scoreTextMatch($titleLower, $tagsLower, $phrase, $tokens);

        $license = $result['license'] ?? null;
        $licenseVersion = $result['license_version'] ?? null;
        $licenseLabel = $license
            ? strtoupper($license).($licenseVersion ? " {$licenseVersion}" : '')
            : null;

        if (! $licenseLabel) {
            $confidence = min($confidence, 40);
        }

        $attribution = $result['attribution']
            ?? ($licenseLabel ? "Licensed under {$licenseLabel}, via Openverse" : null);

        return [
            'url' => $url,
            'source_url' => $result['foreign_landing_url'] ?? $result['detail_url'] ?? null,
            'title' => $title,
            'width' => $width,
            'height' => $height,
            'mime_type' => $result['filetype'] ? "image/{$result['filetype']}" : null,
            'license' => $licenseLabel,
            'attribution' => $attribution,
            'confidence' => max(0, min(100, $confidence)),
        ];
    }

    protected function userAgent(): string
    {
        return $this->config['user_agent'] ?? 'PhoneKinboCatalogueBot/1.0';
    }
}
