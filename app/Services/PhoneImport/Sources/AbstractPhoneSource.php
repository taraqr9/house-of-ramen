<?php

namespace App\Services\PhoneImport\Sources;

use App\Enums\SourceTypeEnum;
use App\Services\PhoneImport\Contracts\PhoneSourceProvider;

abstract class AbstractPhoneSource implements PhoneSourceProvider
{
    public function __construct(
        protected readonly string $sourceKey,
        protected readonly array $config = [],
    ) {}

    public function key(): string
    {
        return $this->sourceKey;
    }

    abstract public function type(): SourceTypeEnum;

    abstract public function fetchBatch(?array $cursor, int $limit): array;
}
