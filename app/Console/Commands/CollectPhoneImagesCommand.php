<?php

namespace App\Console\Commands;

use App\Enums\ImageCollectionOutcomeEnum;
use App\Enums\ImageStatusEnum;
use App\Models\Phone;
use App\Models\PhoneSource;
use App\Services\PhoneImage\PhoneImageCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CollectPhoneImagesCommand extends Command
{
    protected $signature = 'phones:collect-images
        {phone? : A specific phone id or slug. Omit to process every eligible phone.}
        {--limit=50 : Max phones to process this run.}
        {--fresh : Also retry phones whose previous attempt landed in needs_review/rejected.}';

    protected $description = 'Discover, validate, download, and optimize a public product image for phones that don\'t have one yet.';

    public function handle(PhoneImageCollector $collector): int
    {
        $this->ensureImageSourcesRegistered();

        $query = Phone::query()->where('is_active', true);

        if ($phoneArg = $this->argument('phone')) {
            $query->where(fn ($q) => $q->where('id', $phoneArg)->orWhere('slug', $phoneArg));
        } elseif ($this->option('fresh')) {
            $query->whereDoesntHave('images', fn ($q) => $q->where('status', ImageStatusEnum::VERIFIED));
        } else {
            $query->whereDoesntHave('images');
        }

        $phones = $query->orderBy('id')->limit((int) $this->option('limit'))->get();

        if ($phones->isEmpty()) {
            $this->info('No eligible phones to process.');

            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            // Re-attempting: clear prior non-verified rows for these phones
            // so a fresh candidate can become the primary without a unique
            // "one primary per phone" conflict.
            foreach ($phones as $phone) {
                $phone->images()->where('status', '!=', ImageStatusEnum::VERIFIED)->delete();
            }
        }

        $counts = ['verified' => 0, 'needs_review' => 0, 'not_found' => 0];

        $progress = $this->output->createProgressBar($phones->count());
        $progress->start();

        foreach ($phones as $phone) {
            $outcome = $collector->collect($phone);

            match ($outcome) {
                ImageCollectionOutcomeEnum::VERIFIED => $counts['verified']++,
                ImageCollectionOutcomeEnum::NEEDS_REVIEW => $counts['needs_review']++,
                ImageCollectionOutcomeEnum::NOT_FOUND => $counts['not_found']++,
            };

            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);

        $this->table(
            ['Verified', 'Needs Review', 'Not Found'],
            [[$counts['verified'], $counts['needs_review'], $counts['not_found']]]
        );

        return self::SUCCESS;
    }

    /**
     * Registers a phone_sources row for every provenance key any enabled
     * image source declares (config('phone_image_sources.sources.*.phone_source_key')),
     * so a newly-added image provider automatically gets a traceable
     * source row without this command needing to know its name.
     */
    protected function ensureImageSourcesRegistered(): void
    {
        $provenanceKeys = collect(config('phone_image_sources.sources', []))
            ->filter(fn (array $definition) => $definition['enabled'] ?? false)
            ->pluck('phone_source_key')
            ->filter()
            ->unique();

        foreach (config('phone_sources.sources', []) as $key => $definition) {
            if (! $provenanceKeys->contains($key)) {
                continue;
            }

            PhoneSource::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'reliability_score' => $definition['reliability_score'] ?? 50,
                    'requires_review' => $definition['requires_review'] ?? false,
                    'config' => $definition['config'] ?? [],
                ]
            );
        }

        if (! File::isDirectory(storage_path('app/public/phones'))) {
            File::makeDirectory(storage_path('app/public/phones'), 0755, true);
        }
    }
}
