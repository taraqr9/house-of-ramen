<?php

namespace App\Services\PhoneImport\Normalization;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Converts messy, source-specific raw values ("8 GB RAM", "5,000mAh",
 * "৳29,999", "1 TB") into consistent typed values for storage. Every
 * method is defensive: unparsable input returns null rather than
 * throwing, since a source may simply omit a field.
 *
 * The raw value is never discarded by this class - callers are
 * expected to keep it in phone_import_records.raw_payload for audit,
 * these methods only produce the *normalized* value that gets stored
 * on the actual phone/spec/variant/price rows.
 */
class SpecNormalizer
{
    /**
     * Extract RAM in whole gigabytes from strings like "8GB", "8 GB LPDDR5", "8".
     */
    public static function ramGb(mixed $raw): ?int
    {
        return self::wholeGigabytes($raw);
    }

    /**
     * Extract storage in whole gigabytes from strings like "256GB", "1 TB", "1TB UFS 3.1".
     */
    public static function storageGb(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $raw = (string) $raw;

        if (preg_match('/([\d.]+)\s*TB/i', $raw, $matches)) {
            return (int) round(((float) $matches[1]) * 1024);
        }

        return self::wholeGigabytes($raw);
    }

    protected static function wholeGigabytes(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return (int) round((float) $raw);
        }

        if (preg_match('/([\d.]+)\s*GB/i', (string) $raw, $matches)) {
            return (int) round((float) $matches[1]);
        }

        return null;
    }

    /**
     * Extract battery capacity in mAh from strings like "5000 mAh", "5,000mAh".
     */
    public static function batteryMah(mixed $raw): ?int
    {
        return self::firstInteger($raw);
    }

    /**
     * Extract charging wattage from strings like "67W", "67 W fast charging".
     */
    public static function wattage(mixed $raw): ?int
    {
        return self::firstInteger($raw);
    }

    /**
     * Extract display size in inches from strings like "6.7", "6.7 inch", "6.7in".
     */
    public static function displaySizeInches(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return round((float) $raw, 1);
        }

        if (preg_match('/([\d.]+)/', (string) $raw, $matches)) {
            return round((float) $matches[1], 1);
        }

        return null;
    }

    /**
     * Extract refresh rate in Hz from strings like "120Hz", "120 Hz".
     */
    public static function refreshRateHz(mixed $raw): ?int
    {
        return self::firstInteger($raw);
    }

    /**
     * Extract weight in grams from strings like "188g", "188 g".
     */
    public static function weightGrams(mixed $raw): ?int
    {
        return self::firstInteger($raw);
    }

    /**
     * Extract a monetary amount (e.g. Bangladesh Taka) from strings using
     * grouped-comma formatting such as "1,79,999", "৳29,999", "Tk 29999".
     */
    public static function amount(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return (float) $raw;
        }

        $cleaned = preg_replace('/[^\d.]/', '', (string) $raw);

        if ($cleaned === '' || $cleaned === null) {
            return null;
        }

        return (float) $cleaned;
    }

    /**
     * Parse a "162.3 x 79.0 x 8.6" style dimension string into [height, width, thickness] mm.
     *
     * @return array{0: ?float, 1: ?float, 2: ?float}
     */
    public static function dimensionsMm(mixed $raw): array
    {
        if (! $raw) {
            return [null, null, null];
        }

        $parts = preg_split('/\s*[xX×]\s*/', trim((string) $raw));

        return [
            isset($parts[0]) && is_numeric($parts[0]) ? (float) $parts[0] : null,
            isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : null,
            isset($parts[2]) && is_numeric($parts[2]) ? (float) $parts[2] : null,
        ];
    }

    public static function date(mixed $raw): ?Carbon
    {
        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function boolean(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        return in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * Canonical brand/model name: trim, collapse whitespace, fix common casing.
     */
    public static function name(string $raw): string
    {
        return trim(preg_replace('/\s+/', ' ', $raw));
    }

    /**
     * Phone slug from brand + model name, without duplicating the brand
     * when the model name already starts with it (many sources give a
     * "model" that already includes the brand, e.g. brand "Redmi" / model
     * "Redmi Note 14 Pro" - concatenating both blindly produced slugs
     * like "redmi-redmi-note-14-pro"). Mirrors the same rule
     * PhoneRecommendationEngine::displayName() already uses for the
     * equivalent display-name case.
     */
    public static function phoneSlug(string $brandName, string $modelName): string
    {
        $brandName = trim($brandName);
        $modelName = trim($modelName);

        if (str_starts_with(strtolower($modelName), strtolower($brandName))) {
            return self::slug($modelName);
        }

        return self::slug($brandName, $modelName);
    }

    public static function slug(string ...$parts): string
    {
        // "+" is meaningful (e.g. "Note 14 Pro" vs "Note 14 Pro+" are
        // different phones) but Str::slug() drops punctuation silently,
        // which collapsed both to the same slug and caused the importer's
        // exact-slug-match fast path to merge them. Spell it out first.
        $text = str_replace('+', ' plus', implode(' ', array_filter($parts)));

        return Str::slug($text);
    }

    /**
     * Extract a (RAM, storage) pair from a retailer's own product name
     * string, e.g. "Samsung Galaxy S24 Ultra (12/256GB)", "Poco F6 12GB
     * 256GB", "Galaxy S24 Ultra (Titanium Gray | 256GB)". Used to
     * attribute a generically-discovered product page's price to the
     * correct existing variant without ever guessing: see
     * RetailerListingSource::attributeVariant(), which requires an exact
     * match against a phone's known variants and skips rather than
     * assumes when this returns null or a value no variant has.
     *
     * @return array{ram_gb: ?int, storage_gb: ?int}
     */
    public static function extractVariantFromName(string $name): array
    {
        // "12/256GB", "12GB/256GB", "(12/256GB)", "12GB+256GB" - a RAM
        // figure and a storage figure explicitly paired together is the
        // only case confident enough to report both.
        if (preg_match('/\b(\d{1,2})\s*(?:GB)?\s*[\/+]\s*(\d{2,4})\s*GB\b/i', $name, $matches)) {
            return ['ram_gb' => (int) $matches[1], 'storage_gb' => (int) $matches[2]];
        }

        if (preg_match('/\b(\d{1,2})\s*(?:GB)?\s*[\/+]\s*(\d)\s*TB\b/i', $name, $matches)) {
            return ['ram_gb' => (int) $matches[1], 'storage_gb' => (int) $matches[2] * 1024];
        }

        // Storage named on its own (e.g. "(Titanium Gray | 256GB)") -
        // RAM stays unknown rather than assumed.
        if (preg_match('/\b(\d{2,4})\s*GB\b/i', $name, $matches)) {
            return ['ram_gb' => null, 'storage_gb' => (int) $matches[1]];
        }

        if (preg_match('/\b(\d)\s*TB\b/i', $name, $matches)) {
            return ['ram_gb' => null, 'storage_gb' => (int) $matches[1] * 1024];
        }

        return ['ram_gb' => null, 'storage_gb' => null];
    }

    protected static function firstInteger(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return (int) round((float) $raw);
        }

        $cleaned = str_replace(',', '', (string) $raw);

        if (preg_match('/([\d.]+)/', $cleaned, $matches)) {
            return (int) round((float) $matches[1]);
        }

        return null;
    }
}
