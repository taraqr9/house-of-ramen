{{-- Shown for any Analytics subsection whose GA4 call failed (bad/missing
     credentials, network error, quota) - never the raw exception message,
     never a stack trace (see App\Services\Analytics\AnalyticsDashboardService::cachedReport()). --}}
<p class="text-muted small mb-0">Analytics data temporarily unavailable.</p>
