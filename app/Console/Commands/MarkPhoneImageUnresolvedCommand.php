<?php

namespace App\Console\Commands;

use App\Enums\ImageSearchAttemptStatusEnum;
use App\Models\Phone;
use App\Models\PhoneImageSearchAttempt;
use Illuminate\Console\Command;

/**
 * Records that a phone was genuinely, capped-effort searched for an image
 * and nothing trustworthy was found - see PhoneImageSearchAttempt /
 * ImageSearchAttemptStatusEnum. This is what makes
 * Phone::scopeNeedsImageEnrichment() skip it on future runs instead of
 * re-paying the same search cost every session. Never call this as a
 * shortcut for "didn't look" - only after the sources actually tried
 * (Wikimedia Commons API, manufacturer site, one alternate source) are
 * exhausted within the per-phone cap.
 */
class MarkPhoneImageUnresolvedCommand extends Command
{
    protected $signature = 'phones:mark-image-unresolved
        {phone : Phone id or slug}
        {--reason= : Why no trustworthy image was found}
        {--sources=* : Source(s) actually tried, e.g. wikimedia_commons, manufacturer_site, mobiledokan}
        {--candidates=0 : How many candidate images were downloaded and visually checked before giving up}';

    protected $description = 'Record a phone as unresolved after a genuine, capped image search found nothing trustworthy.';

    public function handle(): int
    {
        $identifier = $this->argument('phone');
        $phone = Phone::query()->where('id', $identifier)->orWhere('slug', $identifier)->first();

        if (! $phone) {
            $this->error("No phone found for '{$identifier}'.");

            return self::FAILURE;
        }

        $reason = $this->option('reason');

        if (! $reason) {
            $this->error('--reason is required - record why no trustworthy image was found.');

            return self::FAILURE;
        }

        PhoneImageSearchAttempt::query()->updateOrCreate(
            ['phone_id' => $phone->id],
            [
                'status' => ImageSearchAttemptStatusEnum::UNRESOLVED,
                'unresolved_reason' => $reason,
                'sources_tried' => $this->option('sources') ?: null,
                'candidates_viewed' => (int) $this->option('candidates'),
                'last_attempted_at' => now(),
            ]
        );

        $this->info("Marked '{$phone->name}' (id {$phone->id}) as unresolved: {$reason}");

        return self::SUCCESS;
    }
}
