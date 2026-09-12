<?php

namespace App\Console\Commands;

use App\Enums\ImageCollectionOutcomeEnum;
use App\Models\Phone;
use App\Services\PhoneImage\PhoneImageCollector;
use Illuminate\Console\Command;

/**
 * Attaches one individually-verified image (found and confirmed by hand
 * for one exact phone model) via the same download/optimize/store
 * pipeline the automated collector uses - see
 * PhoneImageCollector::attachManual(). Not a discovery tool: the caller
 * has already found and verified the URL belongs to the exact phone.
 */
class AttachManualPhoneImageCommand extends Command
{
    protected $signature = 'phones:attach-image
        {phone : Phone id or slug}
        {url : Direct image URL to download}
        {--source-url= : Page the image was found on, for attribution}
        {--license= : License string, if known}
        {--attribution= : Attribution string, if known}';

    protected $description = 'Attach one manually-verified product image to a specific phone.';

    public function handle(PhoneImageCollector $collector): int
    {
        $identifier = $this->argument('phone');
        $phone = Phone::query()->where('id', $identifier)->orWhere('slug', $identifier)->first();

        if (! $phone) {
            $this->error("No phone found for '{$identifier}'.");

            return self::FAILURE;
        }

        $outcome = $collector->attachManual(
            $phone,
            $this->argument('url'),
            $this->option('source-url') ?? $this->argument('url'),
            $this->option('license'),
            $this->option('attribution'),
        );

        if ($outcome !== ImageCollectionOutcomeEnum::VERIFIED) {
            $this->error("Could not download/decode the image for '{$phone->name}' from the given URL.");

            return self::FAILURE;
        }

        $this->info("Attached verified image to '{$phone->name}' (id {$phone->id}).");

        return self::SUCCESS;
    }
}
