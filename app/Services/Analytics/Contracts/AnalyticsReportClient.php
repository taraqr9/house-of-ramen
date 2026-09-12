<?php

namespace App\Services\Analytics\Contracts;

/**
 * The one boundary between App\Services\Analytics\AnalyticsDashboardService
 * (all the dashboard's report-shaping logic) and the actual Google
 * Analytics Data API client. Keeping it this narrow - one method, plain
 * arrays in and out, no Google SDK types crossing the boundary - is what
 * lets tests bind a fake implementation and never touch the network (see
 * tests/Feature/Analytics/*), and lets GA4AnalyticsReportClient stay a thin
 * adapter with no report-shaping logic of its own.
 */
interface AnalyticsReportClient
{
    /**
     * Runs one GA4 report and returns each result row as a flat
     * dimension/metric-name => string-value map (GA4's Data API always
     * returns metric values as strings, even for numbers - callers cast as
     * needed). Throws on any failure (auth, network, misconfiguration,
     * quota) - callers are expected to catch and degrade gracefully rather
     * than let a GA4 outage break the admin dashboard.
     *
     * @param  list<string>  $dimensions  GA4 dimension names, e.g. ['customEvent:brand']
     * @param  list<string>  $metrics  GA4 metric names, e.g. ['eventCount']
     * @param  list<string>|null  $eventNames  when given, filters rows to eventName IN (...) - the only
     *                                         filter shape this dashboard needs
     * @return list<array<string, string>>
     */
    public function runReport(
        array $dimensions,
        array $metrics,
        string $startDate,
        string $endDate,
        ?array $eventNames = null,
        int $limit = 25,
        ?string $orderByMetric = null,
    ): array;
}
