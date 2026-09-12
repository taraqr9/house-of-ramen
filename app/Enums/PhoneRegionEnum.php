<?php

namespace App\Enums;

/**
 * The two user-facing "versions" a phone variant is grouped under on the
 * public site. This is a presentation-layer concept only - it is NOT the
 * `phone_variants.region` database column and never rewrites it. The raw
 * column stays free text (Global/Bangladesh/China/null/anything an
 * importer or admin ever typed); resolve() maps that raw value onto one
 * of these two buckets every time the public site needs to decide which
 * group a variant belongs in.
 */
enum PhoneRegionEnum: string
{
    case GLOBAL = 'global';
    case CHINESE = 'chinese';

    /**
     * The signals that mean "Chinese-market version" - matched as
     * case-insensitive substrings so "China", "china", and "Chinese" all
     * match ("chinese" does NOT contain "china" as a substring - c-h-i-n-e
     * vs. c-h-i-n-a - so both forms are listed explicitly rather than
     * relying on one implying the other). The real value seeded in
     * phone_variants.region is exactly "China" for the small number of
     * variants that need it - see database/seed-data. Shared with
     * Phone::chineseMarketPrices()'s SQL LIKE clauses so the
     * database-level and PHP-level rules never drift apart.
     *
     * @var list<string>
     */
    public const CHINESE_SIGNALS = ['china', 'chinese'];

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Global',
            self::CHINESE => 'Chinese',
        };
    }

    /**
     * Maps a raw phone_variants.region value to Global or Chinese. Only
     * one direction carries real risk here: wrongly telling a buyer a
     * Chinese-market import (different warranty/firmware expectations)
     * is the Global version. So Chinese is assigned only on the positive
     * signal above; everything else - null/empty, "Global", "Bangladesh"
     * (a market label, not a distinct hardware/firmware version, per the
     * variant-architecture audit), and any other free-text value nobody
     * has classified yet - resolves to Global. That is already >99% of
     * existing data, and it is the safe default direction: it never
     * invents a Chinese-import claim the data doesn't support.
     */
    public static function resolve(?string $rawRegion): self
    {
        $normalized = strtolower(trim($rawRegion ?? ''));

        foreach (self::CHINESE_SIGNALS as $signal) {
            if (str_contains($normalized, $signal)) {
                return self::CHINESE;
            }
        }

        return self::GLOBAL;
    }
}
