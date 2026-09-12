<?php

namespace App\Services\PhoneImage;

use App\Services\PhoneImage\Contracts\PhoneImageSourceProvider;
use Illuminate\Support\Collection;

class PhoneImageSourceRegistry
{
    /**
     * @return Collection<int, PhoneImageSourceProvider>
     */
    public function enabledProviders(): Collection
    {
        return collect(config('phone_image_sources.sources', []))
            ->filter(fn (array $definition) => $definition['enabled'] ?? false)
            ->map(fn (array $definition, string $key) => $this->resolve($key, $definition))
            ->filter();
    }

    /**
     * The phone_sources.key row a given provider's collected images
     * should be attributed to (see config/phone_sources.php).
     */
    public function phoneSourceKey(string $providerKey): ?string
    {
        return config("phone_image_sources.sources.{$providerKey}.phone_source_key");
    }

    protected function resolve(string $key, array $definition): ?PhoneImageSourceProvider
    {
        $class = $definition['class'] ?? null;

        if (! $class || ! class_exists($class)) {
            return null;
        }

        return new $class($key, $definition['config'] ?? []);
    }
}
