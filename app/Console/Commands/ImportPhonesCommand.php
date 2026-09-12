<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatusEnum;
use App\Enums\ImportRunTypeEnum;
use App\Models\PhoneImportRun;
use App\Models\PhoneSource;
use App\Services\PhoneImport\PhoneImportRunner;
use App\Services\PhoneImport\PhoneSourceRegistry;
use Illuminate\Console\Command;

class ImportPhonesCommand extends Command
{
    protected $signature = 'phones:import
        {source? : Source key to import (see config/phone_sources.php). Omit to run every enabled source.}
        {--type=manual : initial|nightly|manual, recorded on the import run for reporting.}
        {--chunk=20 : Batch size fetched from the source provider at a time.}
        {--fresh : Ignore any resumable (partial/failed) run for this source and start over.}';

    protected $description = 'Run the phone data import pipeline for one or all enabled sources, chunked and resumable.';

    public function handle(PhoneSourceRegistry $registry, PhoneImportRunner $runner): int
    {
        $registry->sync();

        $type = ImportRunTypeEnum::tryFrom($this->option('type')) ?? ImportRunTypeEnum::MANUAL;
        $chunk = max(1, (int) $this->option('chunk'));
        $fresh = (bool) $this->option('fresh');

        $sourceKey = $this->argument('source');
        $providers = $sourceKey ? [$sourceKey] : $registry->enabledProviders()->keys()->all();

        if (empty($providers)) {
            $this->warn('No enabled phone sources to import.');

            return self::SUCCESS;
        }

        $exitCode = self::SUCCESS;

        foreach ($providers as $key) {
            $exitCode = max($exitCode, $this->importSource($key, $type, $chunk, $fresh, $registry, $runner));
        }

        return $exitCode;
    }

    protected function importSource(
        string $key,
        ImportRunTypeEnum $type,
        int $chunk,
        bool $fresh,
        PhoneSourceRegistry $registry,
        PhoneImportRunner $runner,
    ): int {
        $source = PhoneSource::query()->where('key', $key)->first();

        if (! $source) {
            $this->error("Unknown source '{$key}' - is it registered in config/phone_sources.php?");

            return self::FAILURE;
        }

        if (! $source->is_active) {
            $this->line("Skipping disabled source '{$key}'.");

            return self::SUCCESS;
        }

        $provider = $registry->provider($key);

        if (! $provider) {
            $this->error("Source '{$key}' has no resolvable provider class.");

            return self::FAILURE;
        }

        $resumeRun = PhoneImportRun::query()
            ->where('source_id', $source->id)
            ->where(function ($query) {
                $query->whereIn('status', [ImportRunStatusEnum::PARTIAL, ImportRunStatusEnum::FAILED])
                    // A run stuck at RUNNING with no checkpoint update in a while was
                    // abandoned by a process that got killed/timed out (e.g. a slow
                    // retailer-discovery batch outliving an operator's shell/CI
                    // timeout) rather than crashing through the normal catch block -
                    // that never happens, so it's never picked up by the statuses
                    // above and every later invocation restarts the whole source
                    // from scratch, hitting the same slow patch again and again.
                    // Recovering it here lets the next run continue from its last
                    // saved cursor instead.
                    ->orWhere(function ($stale) {
                        $stale->where('status', ImportRunStatusEnum::RUNNING)
                            ->where('updated_at', '<', now()->subMinutes(10));
                    });
            })
            ->latest('id')
            ->first();

        if ($resumeRun && $resumeRun->status === ImportRunStatusEnum::RUNNING) {
            $this->warn("Import run #{$resumeRun->id} for '{$key}' was stuck at RUNNING with no progress since {$resumeRun->updated_at} - treating it as abandoned and resuming from its last checkpoint.");
        }

        if ($resumeRun && $fresh) {
            $resumeRun->update(['status' => ImportRunStatusEnum::FAILED, 'error_message' => 'Superseded by a fresh run.']);
            $resumeRun = null;
        }

        $this->info($resumeRun
            ? "Resuming import run #{$resumeRun->id} for '{$key}' from its last checkpoint..."
            : "Starting import for '{$key}'...");

        try {
            $run = $runner->run($provider, $source, $type, $resumeRun, auth()->id(), $chunk);
        } catch (\Throwable $e) {
            $this->error("Import for '{$key}' failed: {$e->getMessage()}");
            $this->line('Run is saved as PARTIAL and can be resumed by re-running this command.');

            return self::FAILURE;
        }

        $this->table(
            ['Discovered', 'Created', 'Updated', 'Skipped', 'Failed', 'Conflicts', 'Flagged'],
            [[
                $run->total_discovered, $run->total_created, $run->total_updated,
                $run->total_skipped, $run->total_failed, $run->total_conflicts, $run->total_flagged,
            ]]
        );

        $this->info("Import run #{$run->id} for '{$key}' finished with status: {$run->status->value}");

        return self::SUCCESS;
    }
}
