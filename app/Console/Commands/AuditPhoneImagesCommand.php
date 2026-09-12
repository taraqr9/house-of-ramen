<?php

namespace App\Console\Commands;

use App\Enums\ImageStatusEnum;
use App\Enums\ReviewReasonEnum;
use App\Enums\ReviewStatusEnum;
use App\Models\PhoneDataReview;
use App\Models\PhoneImage;
use App\Models\PhoneSource;
use App\Services\PhoneImage\Support\MatchesPhoneImageText;
use Illuminate\Console\Command;

/**
 * Re-checks every verified, automatically-sourced image against the
 * CURRENT text-matching rules (see MatchesPhoneImageText) using the
 * title recoverable from its stored source_url, without re-fetching
 * anything from the source. Exists to catch images that were verified
 * under an earlier, less strict version of the matcher - a mismatch
 * that would be silently invisible otherwise, since a phone doesn't
 * need a verified image to display (see Phone::primaryImage()), so
 * nothing about the public site would surface a stale wrong-model
 * match on its own.
 *
 * Manually-attached images (phone_sources.key = 'manual_research') were
 * verified by direct human/agent confirmation of the exact model, not
 * by text matching, so there's nothing meaningful to re-score - they're
 * skipped. Likewise, a source_url this command can't parse a title out
 * of (currently only Wikimedia Commons file pages are recognised) is
 * left alone rather than guessed at.
 */
class AuditPhoneImagesCommand extends Command
{
    use MatchesPhoneImageText;

    protected $signature = 'phones:audit-images
        {--fix : Downgrade a flagged image to needs_review and reopen its review row instead of only reporting it.}';

    protected $description = 'Re-verify every verified phone image against the current text-matching rules and flag any that no longer clear the bar.';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        $manualSourceId = PhoneSource::query()->where('key', 'manual_research')->value('id');

        $images = PhoneImage::query()
            ->where('status', ImageStatusEnum::VERIFIED)
            ->when($manualSourceId, fn ($q) => $q->where(fn ($qq) => $qq->whereNull('source_id')->orWhere('source_id', '!=', $manualSourceId)))
            ->with('phone.brand')
            ->get();

        $flagged = [];
        $checked = 0;
        $skippedNoTitle = 0;

        foreach ($images as $image) {
            $phone = $image->phone;

            if (! $phone || ! $phone->brand) {
                continue;
            }

            $title = $this->extractTitleFromUrl($image->source_url);

            if ($title === null) {
                $skippedNoTitle++;

                continue;
            }

            $checked++;

            $query = $this->buildSearchQuery($phone);
            $tokens = $this->tokenize($query);
            $phrase = trim(preg_replace('/\s+/', ' ', strtolower($query)));
            $titleLower = $this->normalizeText($title);

            $recomputed = $this->scoreTextMatch($titleLower, '', $phrase, $tokens);

            if ($recomputed < 65) {
                $flagged[] = compact('image', 'phone', 'title', 'recomputed');
            }
        }

        if (empty($flagged)) {
            $this->info("Checked {$checked} auto-sourced verified image(s) - all still match under current rules ({$skippedNoTitle} skipped: no derivable title).");

            return self::SUCCESS;
        }

        $this->warn(count($flagged)." of {$checked} checked verified image(s) no longer clear the match bar under current rules:");

        $rows = [];

        foreach ($flagged as $f) {
            $rows[] = [
                $f['image']->id,
                $f['phone']->id,
                "{$f['phone']->brand->name} {$f['phone']->name}",
                $f['title'],
                $f['image']->match_confidence,
                $f['recomputed'],
            ];

            if ($fix) {
                $this->downgrade($f['image'], $f['phone']->id, $f['title'], $f['recomputed']);
            }
        }

        $this->table(['Image ID', 'Phone ID', 'Phone', 'Derived Title', 'Stored Conf.', 'Recomputed Conf.'], $rows);

        $this->comment($fix
            ? 'Flagged images downgraded to needs_review and queued for review.'
            : 'Dry run - pass --fix to downgrade these to needs_review.');

        return self::SUCCESS;
    }

    protected function downgrade(PhoneImage $image, int $phoneId, string $title, int $recomputed): void
    {
        $image->update(['status' => ImageStatusEnum::NEEDS_REVIEW, 'is_primary' => false]);

        $alreadyQueued = PhoneDataReview::query()
            ->where('phone_id', $phoneId)
            ->where('reason', ReviewReasonEnum::IMAGE_NEEDS_REVIEW)
            ->where('status', ReviewStatusEnum::PENDING)
            ->exists();

        if ($alreadyQueued) {
            return;
        }

        PhoneDataReview::create([
            'phone_id' => $phoneId,
            'reason' => ReviewReasonEnum::IMAGE_NEEDS_REVIEW,
            'similarity_score' => $recomputed,
            'status' => ReviewStatusEnum::PENDING,
            'details' => [
                'image_id' => $image->id,
                'title' => $title,
                'source_url' => $image->source_url,
                'note' => "Re-audit via phones:audit-images: recomputed confidence {$recomputed} under current matching rules no longer clears the auto-publish threshold.",
            ],
        ]);
    }

    protected function extractTitleFromUrl(?string $sourceUrl): ?string
    {
        if (! $sourceUrl) {
            return null;
        }

        $path = parse_url($sourceUrl, PHP_URL_PATH);

        if (! $path || ! str_contains($path, 'File:')) {
            return null;
        }

        $name = substr($path, strpos($path, 'File:') + strlen('File:'));

        return urldecode(str_replace('_', ' ', $name));
    }
}
