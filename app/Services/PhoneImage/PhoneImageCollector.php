<?php

namespace App\Services\PhoneImage;

use App\Enums\ImageCollectionOutcomeEnum;
use App\Enums\ImageSearchAttemptStatusEnum;
use App\Enums\ImageStatusEnum;
use App\Enums\ReviewReasonEnum;
use App\Enums\ReviewStatusEnum;
use App\Models\Phone;
use App\Models\PhoneDataReview;
use App\Models\PhoneImage;
use App\Models\PhoneImageSearchAttempt;
use App\Models\PhoneSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orchestrates image discovery: asks every enabled source for its
 * ranked candidates, merges them into one list ordered by confidence
 * ACROSS all sources, and stores the best one that actually downloads.
 * A weak candidate from one source never blocks a stronger candidate
 * from another - each provider gets a fair look before any decision is
 * made, which is what lets adding a new source (see
 * config/phone_image_sources.php) genuinely raise the verified rate
 * instead of just adding more needs_review noise. A confident match is
 * marked verified and immediately eligible for public display; anything
 * below that candidate's source's confidence threshold is stored as
 * needs_review and flagged through the existing PhoneDataReview queue
 * instead of being silently published.
 */
class PhoneImageCollector
{
    protected const MAX_DIMENSION = 1000;

    protected const WEBP_QUALITY = 82;

    public function __construct(protected readonly PhoneImageSourceRegistry $registry) {}

    public function collect(Phone $phone): ImageCollectionOutcomeEnum
    {
        $ranked = [];

        foreach ($this->registry->enabledProviders() as $provider) {
            foreach ($provider->search($phone) as $candidate) {
                $candidate['_provider'] = $provider;
                $ranked[] = $candidate;
            }
        }

        if (empty($ranked)) {
            return ImageCollectionOutcomeEnum::NOT_FOUND;
        }

        usort($ranked, fn (array $a, array $b) => $b['confidence'] <=> $a['confidence']);

        foreach ($ranked as $candidate) {
            $stored = $this->downloadAndOptimize($candidate['url'], $phone);

            if ($stored === null) {
                continue;
            }

            $provider = $candidate['_provider'];
            $threshold = (int) config("phone_image_sources.sources.{$provider->key()}.confidence_threshold", 65);

            $status = $candidate['confidence'] >= $threshold
                ? ImageStatusEnum::VERIFIED
                : ImageStatusEnum::NEEDS_REVIEW;

            $sourceKey = $this->registry->phoneSourceKey($provider->key());
            $sourceId = $sourceKey ? PhoneSource::query()->where('key', $sourceKey)->value('id') : null;

            // Only a verified image is ever eligible to be shown (see
            // Phone::primaryImage()), so only a verified image is ever born
            // is_primary - a needs_review candidate staying primary was
            // harmless to the public read path but a genuine invariant
            // violation (and, on a phone that already has a verified
            // primary from an earlier manual attach, a latent multi-primary
            // risk). Demote any existing primary first for the same reason
            // attachManual() already does - a fresh verified match should
            // be the one shown, not a stale one left behind.
            if ($status === ImageStatusEnum::VERIFIED) {
                PhoneImage::query()->where('phone_id', $phone->id)->where('is_primary', true)->update(['is_primary' => false]);
            }

            $image = PhoneImage::create([
                'phone_id' => $phone->id,
                'is_primary' => $status === ImageStatusEnum::VERIFIED,
                'sort_order' => 0,
                'disk' => 'public',
                'path' => $stored['path'],
                'external_url' => $candidate['url'],
                'width' => $stored['width'],
                'height' => $stored['height'],
                'file_size_bytes' => $stored['size'],
                'mime_type' => 'image/webp',
                'source_id' => $sourceId,
                'source_url' => $candidate['source_url'],
                'license' => $candidate['license'],
                'attribution' => $candidate['attribution'],
                'match_confidence' => $candidate['confidence'],
                'status' => $status,
            ]);

            if ($status === ImageStatusEnum::NEEDS_REVIEW) {
                // A phone doesn't need a verified image to be shown on the
                // public site (falls back to a placeholder - see
                // Phone::scopePubliclyVisible()), so this flag is a soft
                // "an image candidate exists but wasn't confident enough"
                // note, not a blocking issue. One open row per phone is
                // plenty - a repeated --fresh collection run (e.g. the
                // nightly schedule) shouldn't stack up a fresh row every
                // time it re-attempts the same still-unconfident phone.
                $alreadyQueued = PhoneDataReview::query()
                    ->where('phone_id', $phone->id)
                    ->where('reason', ReviewReasonEnum::IMAGE_NEEDS_REVIEW)
                    ->where('status', ReviewStatusEnum::PENDING)
                    ->exists();

                if (! $alreadyQueued) {
                    PhoneDataReview::create([
                        'phone_id' => $phone->id,
                        'reason' => ReviewReasonEnum::IMAGE_NEEDS_REVIEW,
                        'similarity_score' => $candidate['confidence'],
                        'status' => ReviewStatusEnum::PENDING,
                        'details' => [
                            'image_id' => $image->id,
                            'title' => $candidate['title'],
                            'source_url' => $candidate['source_url'],
                            'license' => $candidate['license'],
                        ],
                    ]);
                }

                return ImageCollectionOutcomeEnum::NEEDS_REVIEW;
            }

            return ImageCollectionOutcomeEnum::VERIFIED;
        }

        return ImageCollectionOutcomeEnum::NOT_FOUND;
    }

    /**
     * Attach a single, individually-verified image found by hand (a
     * targeted web search for one specific phone, cross-checked against
     * the exact model/variant before being passed in here) rather than
     * matched automatically by a provider's text-similarity scoring.
     * Reuses the same download/resize/webp pipeline as collect() so
     * manually- and automatically-sourced images are stored identically -
     * only the confidence-gating step is skipped, because the verification
     * already happened (by a human/agent checking the exact model),
     * not by a text-match score.
     */
    public function attachManual(Phone $phone, string $url, string $sourceUrl, ?string $license, ?string $attribution): ImageCollectionOutcomeEnum
    {
        $stored = $this->downloadAndOptimize($url, $phone);

        if ($stored === null) {
            return ImageCollectionOutcomeEnum::NOT_FOUND;
        }

        $sourceId = PhoneSource::query()->where('key', 'manual_research')->value('id');

        // Demote any earlier needs_review/rejected candidate that was
        // still flagged primary - the new, individually-verified image is
        // the one that should surface everywhere primary images are read.
        PhoneImage::query()->where('phone_id', $phone->id)->where('is_primary', true)->update(['is_primary' => false]);

        PhoneImage::create([
            'phone_id' => $phone->id,
            'is_primary' => true,
            'sort_order' => 0,
            'disk' => 'public',
            'path' => $stored['path'],
            'external_url' => $url,
            'width' => $stored['width'],
            'height' => $stored['height'],
            'file_size_bytes' => $stored['size'],
            'mime_type' => 'image/webp',
            'source_id' => $sourceId,
            'source_url' => $sourceUrl,
            'license' => $license,
            'attribution' => $attribution,
            'match_confidence' => 100,
            'status' => ImageStatusEnum::VERIFIED,
        ]);

        // Record the successful outcome in the same attempt-tracking row
        // a prior unresolved search may have left behind, so the phone's
        // history is complete and it's unambiguously done - no separate
        // command call needed for the success path, only for
        // `phones:mark-image-unresolved` on the give-up path.
        PhoneImageSearchAttempt::query()->updateOrCreate(
            ['phone_id' => $phone->id],
            [
                'status' => ImageSearchAttemptStatusEnum::VERIFIED,
                'unresolved_reason' => null,
                'last_attempted_at' => now(),
            ]
        );

        return ImageCollectionOutcomeEnum::VERIFIED;
    }

    /**
     * @return array{path: string, width: int, height: int, size: int}|null
     */
    protected function downloadAndOptimize(string $url, Phone $phone): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'PhoneKinboCatalogueBot/1.0 (contact: developer@bol-online.com)'])
                ->timeout(20)
                ->retry(2, 500)
                ->get($url);
        } catch (\Throwable $e) {
            Log::channel('custom_error')->warning('Phone image download failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->optimizeAndStore($response->body(), $phone);
    }

    /**
     * Same resize/re-encode/store pipeline as downloadAndOptimize() above,
     * for bytes that are already local (an admin's uploaded file) rather
     * than fetched from a URL - see PhoneImageController::store() /
     * attachUploaded() below. Every phone_images row, whichever path
     * created it, ends up with the same on-disk shape (≤1000px WebP under
     * phones/{phone_id}/...), so the public site never needs to care which
     * pipeline stored a given image.
     *
     * @return array{path: string, width: int, height: int, size: int}|null
     */
    protected function optimizeAndStore(string $bytes, Phone $phone): ?array
    {
        $source = @imagecreatefromstring($bytes);

        if (! $source) {
            return null;
        }

        // Preserve orientation as-is - we never crop or reframe the
        // actual product photo, only cap dimensions for performance. The
        // frontend's consistent aspect-ratio container handles varying
        // source orientations.
        $origWidth = imagesx($source);
        $origHeight = imagesy($source);

        if ($origWidth > self::MAX_DIMENSION || $origHeight > self::MAX_DIMENSION) {
            $ratio = min(self::MAX_DIMENSION / $origWidth, self::MAX_DIMENSION / $origHeight);
            $width = max(1, (int) round($origWidth * $ratio));
            $height = max(1, (int) round($origHeight * $ratio));

            $resized = imagecreatetruecolor($width, $height);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
            imagedestroy($source);
            $source = $resized;
        } else {
            $width = $origWidth;
            $height = $origHeight;
        }

        ob_start();
        imagewebp($source, null, self::WEBP_QUALITY);
        $optimized = ob_get_clean();
        imagedestroy($source);

        if ($optimized === false || $optimized === '') {
            return null;
        }

        $path = "phones/{$phone->id}/".Str::random(16).'.webp';
        Storage::disk('public')->put($path, $optimized);

        return [
            'path' => $path,
            'width' => $width,
            'height' => $height,
            'size' => strlen($optimized),
        ];
    }

    /**
     * Attach an image uploaded directly through the admin phone edit
     * screen (App\Http\Controllers\PhoneImageController::store()). Same
     * "a human has directly confirmed this photo belongs to this phone"
     * reasoning as attachManual() above (there it's a researcher pasting a
     * verified URL; here it's an admin picking a file for this exact
     * phone) - so it's stored verified immediately, not queued through the
     * confidence-gated needs_review path collect() uses for
     * automated/unattended matches.
     */
    public function attachUploaded(Phone $phone, string $bytes, ?int $variantId = null): ImageCollectionOutcomeEnum
    {
        $stored = $this->optimizeAndStore($bytes, $phone);

        if ($stored === null) {
            return ImageCollectionOutcomeEnum::NOT_FOUND;
        }

        $sourceId = PhoneSource::query()->where('key', 'admin_manual')->value('id');

        // Same invariant as collect()/attachManual(): only one verified
        // primary image per phone, so the freshly uploaded one is what the
        // public site actually shows.
        PhoneImage::query()->where('phone_id', $phone->id)->where('is_primary', true)->update(['is_primary' => false]);

        PhoneImage::create([
            'phone_id' => $phone->id,
            'phone_variant_id' => $variantId,
            'is_primary' => true,
            'sort_order' => 0,
            'disk' => 'public',
            'path' => $stored['path'],
            'external_url' => null,
            'width' => $stored['width'],
            'height' => $stored['height'],
            'file_size_bytes' => $stored['size'],
            'mime_type' => 'image/webp',
            'source_id' => $sourceId,
            'source_url' => null,
            'license' => null,
            'attribution' => null,
            'match_confidence' => 100,
            'status' => ImageStatusEnum::VERIFIED,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return ImageCollectionOutcomeEnum::VERIFIED;
    }
}
