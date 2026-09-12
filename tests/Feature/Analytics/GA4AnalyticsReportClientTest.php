<?php

use App\Services\Analytics\GA4AnalyticsReportClient;

/**
 * Config-only tests for the real GA4 client - never constructs a
 * BetaAnalyticsDataClient with real credentials and never makes a network
 * call. See tests/Feature/Analytics/AnalyticsDashboardTest.php for the
 * dashboard behaviour, always exercised against a fake client instead.
 */
it('builds an unconfigured client when GA4_PROPERTY_ID/GA4_CREDENTIALS_PATH are unset, never throwing at construction time', function () {
    config(['analytics.property_id' => null, 'analytics.credentials_path' => null]);

    $client = GA4AnalyticsReportClient::fromConfig();

    expect($client)->toBeInstanceOf(GA4AnalyticsReportClient::class);
});

it('throws only when a report is actually requested from an unconfigured client, not at construction', function () {
    config(['analytics.property_id' => null, 'analytics.credentials_path' => null]);

    $client = GA4AnalyticsReportClient::fromConfig();

    expect(fn () => $client->runReport([], ['eventCount'], 'today', 'today'))
        ->toThrow(RuntimeException::class, 'not configured');
});

it('treats a configured property ID with a non-existent credentials file as unconfigured, never a fatal error', function () {
    config([
        'analytics.property_id' => '123456789',
        'analytics.credentials_path' => '/does/not/exist.json',
    ]);

    $client = GA4AnalyticsReportClient::fromConfig();

    expect(fn () => $client->runReport([], ['eventCount'], 'today', 'today'))
        ->toThrow(RuntimeException::class);
});
