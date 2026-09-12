<?php

namespace App\Services\PhoneImport;

use App\Enums\SourceTypeEnum;
use App\Models\PhoneSource;
use App\Services\PhoneImport\Contracts\PhoneSourceProvider;
use Illuminate\Support\Collection;

/**
 * Bridges config/phone_sources.php (which adapter class backs which
 * source key) with the phone_sources table (the mutable, admin-editable
 * state: is_active, reliability_score, notes). Config is only ever
 * synced INTO the database (create/update by key); it is never used to
 * delete a row, so disabling a source from config simply stops it being
 * resolvable while its history/DB row and any editorial changes remain.
 */
class PhoneSourceRegistry
{
    /**
     * Create or update the phone_sources row for every configured source
     * so the admin UI always reflects what's registered in config. Safe
     * to call repeatedly (e.g. on every import run).
     */
    public function sync(): void
    {
        foreach (config('phone_sources.sources', []) as $key => $definition) {
            $type = SourceTypeEnum::from($definition['type']);
            $source = PhoneSource::query()->where('key', $key)->first();

            if ($source) {
                // reliability_score is admin-editable state (see class
                // docblock) - e.g. DataReviewController::markSourceUnreliable()
                // deliberately lowers it after a real-world confirmation that
                // a source is bad. Config only ever supplies the STARTING
                // value for a brand-new source; re-syncing on every import
                // run must never stomp an editorial adjustment back to the
                // config default.
                $source->update([
                    'name' => $definition['name'],
                    'type' => $type,
                    'config' => $definition['config'] ?? [],
                ]);

                continue;
            }

            PhoneSource::create([
                'key' => $key,
                'name' => $definition['name'],
                'type' => $type,
                'reliability_score' => $definition['reliability_score']
                    ?? config("phone_confidence.default_reliability_by_type.{$type->value}", 50),
                'requires_review' => $definition['requires_review'] ?? false,
                'config' => $definition['config'] ?? [],
            ]);
        }
    }

    /**
     * @return Collection<int, PhoneSourceProvider>
     */
    public function enabledProviders(): Collection
    {
        return collect(config('phone_sources.sources', []))
            ->filter(fn (array $definition) => $definition['enabled'] ?? false)
            ->filter(fn (array $definition, string $key) => $this->isActiveInDatabase($key))
            ->map(fn (array $definition, string $key) => $this->resolve($key, $definition));
    }

    public function provider(string $key): ?PhoneSourceProvider
    {
        $definition = config("phone_sources.sources.{$key}");

        return $definition ? $this->resolve($key, $definition) : null;
    }

    protected function resolve(string $key, array $definition): PhoneSourceProvider
    {
        $class = $definition['class'];

        return new $class($key, $definition['config'] ?? []);
    }

    protected function isActiveInDatabase(string $key): bool
    {
        $source = PhoneSource::query()->where('key', $key)->first();

        // Not yet synced to the database - fall back to config's `enabled` flag.
        return $source?->is_active ?? true;
    }
}
