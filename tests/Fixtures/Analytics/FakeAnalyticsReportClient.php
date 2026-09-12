<?php

namespace Tests\Fixtures\Analytics;

use App\Services\Analytics\Contracts\AnalyticsReportClient;
use Throwable;

/**
 * Test double for AnalyticsReportClient - bound in place of
 * GA4AnalyticsReportClient (see tests/Feature/Analytics/*) so the suite
 * never makes a real Google Analytics Data API call. Canned responses are
 * registered per (dimensions, eventNames) signature - the same shape
 * AnalyticsDashboardService actually calls with - so a test can control
 * exactly what one report call returns, including throwing to simulate a
 * GA4 failure.
 */
class FakeAnalyticsReportClient implements AnalyticsReportClient
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    /**
     * @param  array<string, list<array<string, string>>|Throwable>  $responses
     */
    public function __construct(private array $responses = []) {}

    public function respond(array $dimensions, ?array $eventNames, array|Throwable $response): void
    {
        $this->responses[$this->signature($dimensions, $eventNames)] = $response;
    }

    public function runReport(
        array $dimensions,
        array $metrics,
        string $startDate,
        string $endDate,
        ?array $eventNames = null,
        int $limit = 25,
        ?string $orderByMetric = null,
    ): array {
        $this->calls[] = compact('dimensions', 'metrics', 'startDate', 'endDate', 'eventNames', 'limit', 'orderByMetric');

        $response = $this->responses[$this->signature($dimensions, $eventNames)] ?? [];

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }

    private function signature(array $dimensions, ?array $eventNames): string
    {
        return implode(',', $dimensions).'|'.implode(',', $eventNames ?? []);
    }
}
