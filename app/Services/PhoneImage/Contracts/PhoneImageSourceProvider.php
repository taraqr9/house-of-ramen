<?php

namespace App\Services\PhoneImage\Contracts;

use App\Models\Phone;

/**
 * A pluggable image source. Given a phone, returns ranked candidate
 * images with enough provenance to store and (if confident enough)
 * publish. Never called directly by the public site - only by
 * PhoneImageCollector via the artisan import pipeline.
 */
interface PhoneImageSourceProvider
{
    /**
     * Machine key matching this source's config/phone_image_sources.php
     * entry and (via phone_source_key) a phone_sources row.
     */
    public function key(): string;

    /**
     * @return list<array{
     *     url: string,
     *     source_url: string,
     *     title: string,
     *     width: int|null,
     *     height: int|null,
     *     mime_type: string|null,
     *     license: string|null,
     *     attribution: string|null,
     *     confidence: int,
     * }> ranked best-match first
     */
    public function search(Phone $phone): array;
}
