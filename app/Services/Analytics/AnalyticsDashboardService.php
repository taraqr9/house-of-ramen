<?php

namespace App\Services\Analytics;

use App\Models\Phone;
use App\Services\Analytics\Contracts\AnalyticsReportClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds every section of the admin dashboard's Analytics card from GA4
 * (see resources/views/dashboard.blade.php) - the single place that knows
 * which GA4 event/dimension/metric names back which section, so the events
 * actually sent by the public site (resources/js/utils/analytics.js and
 * friends) and what this dashboard queries can never quietly drift apart.
 *
 * Every section is wrapped in caching + a try/catch that degrades to an
 * 'error' status rather than let a GA4 outage or missing configuration
 * break the rest of the admin dashboard (see cachedReport()). An empty-but-
 * successful GA4 response is a distinct 'empty' status, never conflated
 * with an error (see AnalyticsSectionResult).
 */
class AnalyticsDashboardService
{
    public function __construct(
        private readonly AnalyticsReportClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(AnalyticsDateRange $range): array
    {
        $findMyPhoneTotals = $this->findMyPhoneTotals($range);

        return [
            'range' => $range,
            'overview' => $this->overview($range),
            'visitors_over_time' => $this->visitorsOverTime($range),
            'most_viewed_phones' => $this->mostViewedPhones($range),
            'budget_ranges' => $this->budgetRanges($range),
            'brand_views' => $this->brandViews($range),
            'top_searches' => $this->topSearches($range),
            'compared_phones' => $this->comparedPhones($range),
            'find_my_phone' => [
                'status' => $findMyPhoneTotals['status'],
                'started' => $findMyPhoneTotals['started'],
                'completed' => $findMyPhoneTotals['completed'],
                'completion_rate' => $findMyPhoneTotals['started'] > 0
                    ? round($findMyPhoneTotals['completed'] / $findMyPhoneTotals['started'] * 100, 1)
                    : null,
                'recommendation_views' => $findMyPhoneTotals['recommendation_views'],
                'recommendation_clicks' => $findMyPhoneTotals['recommendation_clicks'],
                'budget_ranges' => $this->findMyPhoneBreakdown($range, 'customEvent:budget_range', fn (string $value) => self::budgetRangeLabel($value)),
                'usage_categories' => $this->findMyPhoneBreakdown($range, 'customEvent:usage_category'),
                'brands' => $this->findMyPhoneMultiValueBreakdown($range, 'customEvent:preferred_brands'),
            ],
        ];
    }

    /**
     * Users / new users / sessions / page views / engagement - standard GA4
     * account-level metrics, not tied to any of the app's own custom
     * events, so they need no custom-dimension registration to work. An
     * empty response (brand new property, genuinely zero traffic) is shown
     * as honest zeros, never as an error.
     */
    private function overview(AnalyticsDateRange $range): array
    {
        $result = $this->cachedReport("overview:{$range->key}", fn () => $this->client->runReport(
            dimensions: [],
            metrics: ['activeUsers', 'newUsers', 'sessions', 'screenPageViews', 'engagementRate'],
            startDate: $range->startDate,
            endDate: $range->endDate,
        ));

        if ($result === null) {
            return ['status' => 'error'];
        }

        $row = $result[0] ?? [];

        return [
            'status' => 'ok',
            'users' => (int) ($row['activeUsers'] ?? 0),
            'new_users' => (int) ($row['newUsers'] ?? 0),
            'sessions' => (int) ($row['sessions'] ?? 0),
            'page_views' => (int) ($row['screenPageViews'] ?? 0),
            'engagement_rate' => round((float) ($row['engagementRate'] ?? 0) * 100, 1),
        ];
    }

    /**
     * A small, single-glance summary for the main /admin dashboard's
     * Analytics card (see resources/views/dashboard.blade.php) - the full
     * breakdowns live only on the dedicated /admin/analytics page
     * (App\Http\Controllers\AnalyticsController). Reuses overview()/
     * budgetRanges()/mostViewedPhones() rather than issuing its own report
     * calls, so visiting both pages for the same date range never doubles
     * the number of real GA4 requests (each is already cached).
     */
    public function summary(AnalyticsDateRange $range): array
    {
        $overview = $this->overview($range);
        $topBudget = $this->budgetRanges($range);
        $topPhone = $this->mostViewedPhones($range);

        return [
            'status' => $overview['status'],
            'users' => $overview['users'] ?? null,
            'sessions' => $overview['sessions'] ?? null,
            'page_views' => $overview['page_views'] ?? null,
            'top_budget_range' => $topBudget['rows'][0]['label'] ?? null,
            'top_phone_name' => $topPhone['rows'][0]['phone_name'] ?? null,
        ];
    }

    /**
     * Daily active users/sessions for the period, for the Analytics page's
     * "Visitors" chart (rendered with ApexCharts, already loaded globally
     * by the admin theme - see resources/views/analytics/index.blade.php -
     * no new charting dependency). GA4's `date` dimension returns
     * "YYYYMMDD" strings with no ordering guarantee, so rows are sorted
     * here rather than requested pre-sorted.
     */
    private function visitorsOverTime(AnalyticsDateRange $range): array
    {
        $rows = $this->cachedReport("visitors_over_time:{$range->key}", fn () => $this->client->runReport(
            dimensions: ['date'],
            metrics: ['activeUsers', 'sessions'],
            startDate: $range->startDate,
            endDate: $range->endDate,
            limit: 31,
        ));

        if ($rows === null) {
            return ['status' => 'error', 'rows' => []];
        }

        $shaped = collect($rows)
            ->map(fn (array $row) => [
                'date' => $row['date'] ?? '',
                'label' => self::formatGa4Date($row['date'] ?? ''),
                'users' => (int) ($row['activeUsers'] ?? 0),
                'sessions' => (int) ($row['sessions'] ?? 0),
            ])
            ->sortBy('date')
            ->values()
            ->all();

        return ['status' => empty($shaped) ? 'empty' : 'ok', 'rows' => $shaped];
    }

    private static function formatGa4Date(string $value): string
    {
        if (! preg_match('/^\d{8}$/', $value)) {
            return $value;
        }

        return Carbon::createFromFormat('Ymd', $value)->format('M j');
    }

    /**
     * The view_phone event, broken down by the phone_id/phone_name/brand
     * params it already carries (resources/js/Pages/Public/Phones/Show.vue)
     * - requires phone_id, phone_name, and brand registered as GA4
     * event-scoped custom dimensions (see README.md). Links back to the
     * admin phone edit page only for phones that still exist, via one batch
     * existence query rather than one query per row.
     */
    private function mostViewedPhones(AnalyticsDateRange $range): array
    {
        $rows = $this->cachedReport("view_phone:{$range->key}", fn () => $this->client->runReport(
            dimensions: ['customEvent:phone_id', 'customEvent:phone_name', 'customEvent:brand'],
            metrics: ['eventCount'],
            startDate: $range->startDate,
            endDate: $range->endDate,
            eventNames: ['view_phone'],
            limit: 10,
            orderByMetric: 'eventCount',
        ));

        if ($rows === null) {
            return ['status' => 'error', 'rows' => []];
        }

        $phoneIds = array_filter(array_map(fn (array $row) => (int) ($row['customEvent:phone_id'] ?? 0), $rows));
        $existingIds = Phone::query()->whereIn('id', $phoneIds)->pluck('id')->flip();

        $shaped = array_map(fn (array $row) => [
            'phone_id' => (int) ($row['customEvent:phone_id'] ?? 0),
            'phone_name' => $row['customEvent:phone_name'] ?? 'Unknown phone',
            'brand' => $row['customEvent:brand'] ?? null,
            'views' => (int) ($row['eventCount'] ?? 0),
            'admin_url' => isset($existingIds[(int) ($row['customEvent:phone_id'] ?? 0)])
                ? route('phones.edit', (int) $row['customEvent:phone_id'])
                : null,
        ], $rows);

        return ['status' => empty($shaped) ? 'empty' : 'ok', 'rows' => $shaped];
    }

    /**
     * budget_selected across every source (homepage, /phones filters, Find
     * My Phone) - the same event/taxonomy resources/js/utils/budget.js
     * feeds, never a second budget definition. Percentages are computed
     * here from the returned counts, not requested from GA4 directly.
     */
    private function budgetRanges(AnalyticsDateRange $range): array
    {
        $rows = $this->cachedReport("budget_selected:{$range->key}", fn () => $this->client->runReport(
            dimensions: ['customEvent:budget_range'],
            metrics: ['eventCount'],
            startDate: $range->startDate,
            endDate: $range->endDate,
            eventNames: ['budget_selected'],
            limit: 20,
            orderByMetric: 'eventCount',
        ));

        if ($rows === null) {
            return ['status' => 'error', 'rows' => []];
        }

        return ['status' => empty($rows) ? 'empty' : 'ok', 'rows' => $this->withPercentages($rows, 'customEvent:budget_range', fn (string $value) => self::budgetRangeLabel($value))];
    }

    /**
     * brand_viewed (fired from the brand landing page,
     * Pages/Public/Phones/Brand.vue) - the most direct "attention on a
     * brand" signal already tracked, so this reuses it rather than
     * introducing a second brand-tracking event.
     */
    private function brandViews(AnalyticsDateRange $range): array
    {
        $rows = $this->cachedReport("brand_viewed:{$range->key}", fn () => $this->client->runReport(
            dimensions: ['customEvent:brand'],
            metrics: ['eventCount'],
            startDate: $range->startDate,
            endDate: $range->endDate,
            eventNames: ['brand_viewed'],
            limit: 10,
            orderByMetric: 'eventCount',
        ));

        if ($rows === null) {
            return ['status' => 'error', 'rows' => []];
        }

        $shaped = array_map(fn (array $row) => [
            'brand' => $row['customEvent:brand'] ?? 'Unknown',
            'views' => (int) ($row['eventCount'] ?? 0),
        ], $rows);

        return ['status' => empty($shaped) ? 'empty' : 'ok', 'rows' => $shaped];
    }

    private function topSearches(AnalyticsDateRange $range): array
    {
        $rows = $this->cachedReport("search:{$range->key}", fn () => $this->client->runReport(
            dimensions: ['customEvent:search_term'],
            metrics: ['eventCount'],
            startDate: $range->startDate,
            endDate: $range->endDate,
            eventNames: ['search'],
            limit: 10,
            orderByMetric: 'eventCount',
        ));

        if ($rows === null) {
            return ['status' => 'error', 'rows' => []];
        }

        $shaped = array_map(fn (array $row) => [
            'term' => $row['customEvent:search_term'] ?? '',
            'count' => (int) ($row['eventCount'] ?? 0),
        ], $rows);

        return ['status' => empty($shaped) ? 'empty' : 'ok', 'rows' => $shaped];
    }

    /**
     * GA4 only ever gives back the exact string value sent per event - for
     * compare_phones that's a comma-joined list of every phone in one
     * comparison (resources/js/Pages/Public/Compare/Index.vue), because a
     * GA4 event parameter can't hold an array. There is no reliable way to
     * reconstruct which SPECIFIC pairs were compared together from that
     * (a 3- or 4-phone comparison has no single unambiguous pair
     * breakdown), so this deliberately does not attempt "phone A vs phone
     * B" data. What IS reliable: exploding each row's comma-joined phone
     * list and tallying how often each individual phone appeared in any
     * comparison, weighted by that row's real eventCount - a faithful
     * reconstruction of the actual tracked data, not an invented metric.
     */
    private function comparedPhones(AnalyticsDateRange $range): array
    {
        $rows = $this->cachedReport("compare_phones:{$range->key}", fn () => $this->client->runReport(
            dimensions: ['customEvent:phone_ids', 'customEvent:phone_names', 'customEvent:brands'],
            metrics: ['eventCount'],
            startDate: $range->startDate,
            endDate: $range->endDate,
            eventNames: ['compare_phones'],
            limit: 100,
        ));

        if ($rows === null) {
            return ['status' => 'error', 'rows' => []];
        }

        $tally = [];

        foreach ($rows as $row) {
            $ids = explode(',', $row['customEvent:phone_ids'] ?? '');
            $names = explode(',', $row['customEvent:phone_names'] ?? '');
            $brands = explode(',', $row['customEvent:brands'] ?? '');
            $count = (int) ($row['eventCount'] ?? 0);

            foreach ($ids as $index => $id) {
                if ($id === '') {
                    continue;
                }

                $tally[$id] ??= ['phone_name' => $names[$index] ?? $id, 'brand' => $brands[$index] ?? null, 'count' => 0];
                $tally[$id]['count'] += $count;
            }
        }

        usort($tally, fn (array $a, array $b) => $b['count'] <=> $a['count']);
        $shaped = array_slice(array_values($tally), 0, 10);

        return ['status' => empty($shaped) ? 'empty' : 'ok', 'rows' => $shaped];
    }

    /**
     * One combined request for every simple Find My Phone/recommendation
     * event count (started, completed, recommendation views/clicks) -
     * dimensioning by eventName and filtering to just these four keeps this
     * to a single GA4 call instead of four.
     */
    private function findMyPhoneTotals(AnalyticsDateRange $range): array
    {
        $eventNames = ['find_phone_started', 'find_phone_completed', 'recommendation_viewed', 'recommendation_phone_clicked'];

        $rows = $this->cachedReport("find_phone_totals:{$range->key}", fn () => $this->client->runReport(
            dimensions: ['eventName'],
            metrics: ['eventCount'],
            startDate: $range->startDate,
            endDate: $range->endDate,
            eventNames: $eventNames,
            limit: count($eventNames),
        ));

        if ($rows === null) {
            return ['status' => 'error', 'started' => 0, 'completed' => 0, 'recommendation_views' => 0, 'recommendation_clicks' => 0];
        }

        $counts = collect($rows)->pluck('eventCount', 'eventName')->map(fn ($count) => (int) $count);

        return [
            'status' => 'ok',
            'started' => $counts->get('find_phone_started', 0),
            'completed' => $counts->get('find_phone_completed', 0),
            'recommendation_views' => $counts->get('recommendation_viewed', 0),
            'recommendation_clicks' => $counts->get('recommendation_phone_clicked', 0),
        ];
    }

    /**
     * A single-value breakdown of find_phone_completed by one of its own
     * non-PII preference parameters (budget_range or usage_category).
     */
    private function findMyPhoneBreakdown(AnalyticsDateRange $range, string $dimension, ?\Closure $labelFor = null): array
    {
        $rows = $this->cachedReport("find_phone_completed:{$dimension}:{$range->key}", fn () => $this->client->runReport(
            dimensions: [$dimension],
            metrics: ['eventCount'],
            startDate: $range->startDate,
            endDate: $range->endDate,
            eventNames: ['find_phone_completed'],
            limit: 15,
            orderByMetric: 'eventCount',
        ));

        if ($rows === null) {
            return ['status' => 'error', 'rows' => []];
        }

        return ['status' => empty($rows) ? 'empty' : 'ok', 'rows' => $this->withPercentages($rows, $dimension, $labelFor)];
    }

    /**
     * Same comma-joined-value reconstruction as comparedPhones() above, for
     * find_phone_completed's preferred_brands parameter (also a possible
     * multi-value list, e.g. "Samsung,Xiaomi").
     */
    private function findMyPhoneMultiValueBreakdown(AnalyticsDateRange $range, string $dimension): array
    {
        $rows = $this->cachedReport("find_phone_completed:{$dimension}:{$range->key}", fn () => $this->client->runReport(
            dimensions: [$dimension],
            metrics: ['eventCount'],
            startDate: $range->startDate,
            endDate: $range->endDate,
            eventNames: ['find_phone_completed'],
            limit: 50,
        ));

        if ($rows === null) {
            return ['status' => 'error', 'rows' => []];
        }

        $tally = [];

        foreach ($rows as $row) {
            $count = (int) ($row['eventCount'] ?? 0);

            foreach (explode(',', $row[$dimension] ?? '') as $value) {
                if ($value === '') {
                    continue;
                }

                $tally[$value] = ($tally[$value] ?? 0) + $count;
            }
        }

        arsort($tally);
        $shaped = array_slice(array_map(fn ($label, $count) => ['label' => $label, 'count' => $count], array_keys($tally), $tally), 0, 10);

        return ['status' => empty($shaped) ? 'empty' : 'ok', 'rows' => $shaped];
    }

    /**
     * @return list<array{label: string, count: int, percent: float}>
     */
    private function withPercentages(array $rows, string $dimension, ?\Closure $labelFor = null): array
    {
        $total = array_sum(array_map(fn (array $row) => (int) ($row['eventCount'] ?? 0), $rows));

        return array_values(array_map(function (array $row) use ($dimension, $total, $labelFor) {
            $count = (int) ($row['eventCount'] ?? 0);
            $rawValue = $row[$dimension] ?? '';

            return [
                'label' => $labelFor ? $labelFor($rawValue) : $rawValue,
                'count' => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ];
        }, $rows));
    }

    /**
     * Formats one budget_range value (already in the "min-max" / "min+" /
     * "any" shape resources/js/utils/budget.js sends - see budgetRangeFor())
     * into a display label. Purely cosmetic: it never reinterprets the
     * boundaries themselves, so the taxonomy stays entirely defined by
     * budgetRangeFor()/config('phone_kinbo.price_brackets'), not duplicated
     * here.
     */
    private static function budgetRangeLabel(string $value): string
    {
        if ($value === 'any' || $value === '') {
            return 'No budget specified';
        }

        if (str_ends_with($value, '+')) {
            return '৳'.number_format((float) rtrim($value, '+')).'+';
        }

        [$min, $max] = array_pad(explode('-', $value, 2), 2, null);

        if ($min === null || $max === null) {
            return $value;
        }

        return '৳'.number_format((float) $min).' – ৳'.number_format((float) $max);
    }

    /**
     * Caches a successful report response for config('analytics.cache_ttl')
     * seconds, keyed per report + date range. Returns null (never throws)
     * on any failure - a missing/invalid GA4 configuration, an API/network
     * error, or an auth failure all collapse to the same "temporarily
     * unavailable" signal for callers, logged once via the app's normal
     * error channel rather than surfaced to the browser.
     */
    private function cachedReport(string $key, \Closure $callback): ?array
    {
        try {
            return Cache::remember("analytics-dashboard:{$key}", config('analytics.cache_ttl'), $callback);
        } catch (Throwable $e) {
            Log::channel('custom_error')->error('GA4 Data API report failed', [
                'report' => $key,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
