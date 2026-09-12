<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\AnalyticsReportClient;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\InListFilter;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Auth\Credentials\ServiceAccountCredentials;
use RuntimeException;

/**
 * Real Google Analytics Data API v1beta client (google/analytics-data,
 * Google's official PHP client) - server-side only, called exclusively from
 * App\Services\Analytics\AnalyticsDashboardService for the admin dashboard.
 * REST transport is forced explicitly (BetaAnalyticsDataClient defaults to
 * gRPC when the PECL grpc extension is present, but this app doesn't
 * require that extension to be installed).
 */
class GA4AnalyticsReportClient implements AnalyticsReportClient
{
    private const SCOPES = ['https://www.googleapis.com/auth/analytics.readonly'];

    public function __construct(
        private readonly ?BetaAnalyticsDataClient $client,
        private readonly ?string $propertyId,
    ) {}

    /**
     * Builds the client from config('analytics.*') (see config/analytics.php
     * and .env.example) - never eagerly connects, so a missing/invalid
     * configuration only surfaces as a runReport() exception at call time,
     * which AnalyticsDashboardService already treats as "temporarily
     * unavailable" rather than an app-breaking error.
     */
    public static function fromConfig(): self
    {
        $propertyId = config('analytics.property_id');
        $credentialsPath = config('analytics.credentials_path');

        if (! $propertyId || ! $credentialsPath || ! is_file($credentialsPath)) {
            return new self(null, null);
        }

        $credentials = new ServiceAccountCredentials(self::SCOPES, $credentialsPath);

        return new self(
            new BetaAnalyticsDataClient(['credentials' => $credentials, 'transport' => 'rest']),
            (string) $propertyId,
        );
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
        if (! $this->client || ! $this->propertyId) {
            throw new RuntimeException('Google Analytics Data API is not configured - see config/analytics.php and .env.example.');
        }

        $request = (new RunReportRequest)
            ->setProperty('properties/'.$this->propertyId)
            ->setDimensions(array_map(fn (string $name) => new Dimension(['name' => $name]), $dimensions))
            ->setMetrics(array_map(fn (string $name) => new Metric(['name' => $name]), $metrics))
            ->setDateRanges([new DateRange(['start_date' => $startDate, 'end_date' => $endDate])])
            ->setLimit($limit);

        if ($eventNames !== null) {
            $request->setDimensionFilter(new FilterExpression([
                'filter' => new Filter([
                    'field_name' => 'eventName',
                    'in_list_filter' => new InListFilter(['values' => $eventNames]),
                ]),
            ]));
        }

        if ($orderByMetric !== null) {
            $request->setOrderBys([
                new OrderBy([
                    'metric' => new MetricOrderBy(['metric_name' => $orderByMetric]),
                    'desc' => true,
                ]),
            ]);
        }

        $response = $this->client->runReport($request);

        $dimensionNames = array_map(fn ($header) => $header->getName(), iterator_to_array($response->getDimensionHeaders()));
        $metricNames = array_map(fn ($header) => $header->getName(), iterator_to_array($response->getMetricHeaders()));

        $rows = [];

        foreach ($response->getRows() as $row) {
            $values = [];

            foreach (iterator_to_array($row->getDimensionValues()) as $index => $dimensionValue) {
                $values[$dimensionNames[$index]] = $dimensionValue->getValue();
            }

            foreach (iterator_to_array($row->getMetricValues()) as $index => $metricValue) {
                $values[$metricNames[$index]] = $metricValue->getValue();
            }

            $rows[] = $values;
        }

        return $rows;
    }
}
