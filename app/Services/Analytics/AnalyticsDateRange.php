<?php

namespace App\Services\Analytics;

/**
 * The dashboard's date-range picker (Today / Last 7 Days / Last 30 Days) -
 * GA4's own relative date keywords ("today", "7daysAgo", ...) are passed
 * straight through as the report's start_date, so the Data API resolves
 * "today" against the property's own configured timezone rather than this
 * app doing that date math itself.
 */
final class AnalyticsDateRange
{
    private const OPTIONS = [
        'today' => ['label' => 'Today', 'start' => 'today'],
        '7d' => ['label' => 'Last 7 Days', 'start' => '7daysAgo'],
        '30d' => ['label' => 'Last 30 Days', 'start' => '30daysAgo'],
    ];

    public const DEFAULT_KEY = '7d';

    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $startDate,
        public readonly string $endDate,
    ) {}

    public static function fromKey(?string $key): self
    {
        $key = array_key_exists((string) $key, self::OPTIONS) ? $key : self::DEFAULT_KEY;
        $option = self::OPTIONS[$key];

        return new self($key, $option['label'], $option['start'], 'today');
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::OPTIONS)
            ->map(fn (array $option, string $key) => ['key' => $key, 'label' => $option['label']])
            ->values()
            ->all();
    }
}
