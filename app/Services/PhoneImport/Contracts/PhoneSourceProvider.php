<?php

namespace App\Services\PhoneImport\Contracts;

use App\Enums\SourceTypeEnum;

/**
 * A pluggable phone data source. Implementations are independently
 * enable/disable-able via config/phone_sources.php and never called
 * directly by the pipeline outside of PhoneImportRunner, which drives
 * them one batch at a time so a single source can never block the
 * others or blow past shared-hosting execution limits.
 */
interface PhoneSourceProvider
{
    /**
     * Machine key matching this source's config/phone_sources.php entry
     * and phone_sources.key row.
     */
    public function key(): string;

    public function type(): SourceTypeEnum;

    /**
     * Fetch the next batch of raw phone records.
     *
     * The cursor is an opaque array previously returned by this same
     * method (null on the first call for a fresh run). Implementations
     * decide what it contains (page number, offset, last external_ref,
     * etc.) - the pipeline only ever round-trips it verbatim, which is
     * what makes an interrupted run resumable.
     *
     * @param  array<string, mixed>|null  $cursor
     * @return array{items: list<array<string, mixed>>, cursor: array<string, mixed>|null, done: bool}
     */
    public function fetchBatch(?array $cursor, int $limit): array;
}
