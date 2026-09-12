<?php

use App\Services\Analytics\AnalyticsDateRange;

it('maps each supported range key to the matching GA4 relative date keywords', function () {
    expect(AnalyticsDateRange::fromKey('today'))
        ->key->toBe('today')
        ->startDate->toBe('today')
        ->endDate->toBe('today');

    expect(AnalyticsDateRange::fromKey('7d'))->startDate->toBe('7daysAgo');
    expect(AnalyticsDateRange::fromKey('30d'))->startDate->toBe('30daysAgo');
});

it('falls back to the default range for a missing or invalid key, never an invalid GA4 date', function () {
    expect(AnalyticsDateRange::fromKey(null)->key)->toBe(AnalyticsDateRange::DEFAULT_KEY);
    expect(AnalyticsDateRange::fromKey('not-a-real-range')->key)->toBe(AnalyticsDateRange::DEFAULT_KEY);
});

it('lists exactly the three date range options the dashboard requires: today, 7 days, 30 days', function () {
    $keys = collect(AnalyticsDateRange::options())->pluck('key')->all();

    expect($keys)->toBe(['today', '7d', '30d']);
});
