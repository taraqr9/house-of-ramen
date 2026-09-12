<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsDashboardService;
use App\Services\Analytics\AnalyticsDateRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The dedicated GA4 reporting page (/admin/analytics) - split out from
 * DashboardController so the main /admin dashboard stays focused on
 * catalogue/admin work (see resources/views/dashboard.blade.php's small
 * Analytics summary card + "View Analytics ->" link). Same permission gate,
 * same AnalyticsDashboardService/caching/GA4 client as before - this is a
 * presentation split only, not a second analytics backend.
 */
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsDashboardService $analytics,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(auth()->user()->can('dashboard-view'), 403);

        $range = AnalyticsDateRange::fromKey($request->query('range'));

        return view('analytics.index', [
            'page_title' => 'Analytics',
            'analytics' => $this->analytics->snapshot($range),
            'analyticsRangeOptions' => AnalyticsDateRange::options(),
        ]);
    }
}
