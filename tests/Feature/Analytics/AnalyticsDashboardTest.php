<?php

use App\Services\Analytics\Contracts\AnalyticsReportClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The dedicated /admin/analytics page (App\Http\Controllers\AnalyticsController)
 * - full GA4 breakdowns. See tests/Feature/Analytics/DashboardAnalyticsSummaryTest.php
 * for the small summary card that replaced this on the main /admin dashboard.
 * bindFakeAnalytics()/fullAnalyticsResponses() live in tests/Pest.php so both
 * files share the exact same canned GA4 fixtures.
 */
it('blocks a guest from the analytics page entirely', function () {
    $this->get('/admin/analytics')->assertRedirect('/login');
});

it('blocks an authenticated user without dashboard-view from the analytics page', function () {
    $user = phoneDataUser(['phone-view']); // no dashboard-view

    $this->actingAs($user)->get('/admin/analytics')->assertForbidden();
});

it('renders every analytics section for an authorized admin with real GA4 data', function () {
    bindFakeAnalytics(fullAnalyticsResponses());
    $user = phoneDataUser(['dashboard-view']);

    $response = $this->actingAs($user)->get('/admin/analytics');

    $response->assertOk();
    $response->assertViewIs('analytics.index');
    $response->assertSee('Analytics');
    $response->assertSee('Visitors');
    $response->assertSee('Most Viewed Phones');
    $response->assertSee('iPhone 16 Pro');
    $response->assertSee('Most Popular Budget Ranges');
    $response->assertSee('৳20,000', false); // budgetRangeLabel() formatting of "20000-30000"
    $response->assertSee('63.6%'); // 42 / (42 + 24) rounded to one decimal
    $response->assertSee('Most Viewed Brands');
    $response->assertSee('Top Searches');
    $response->assertSee('galaxy a55');
    $response->assertSee('Most Compared Phones');
    $response->assertSee('Find My Phone');
});

it('does not require phone-view - analytics is gated only by dashboard-view', function () {
    bindFakeAnalytics(fullAnalyticsResponses());
    $user = phoneDataUser(['dashboard-view']); // deliberately no phone-view

    $this->actingAs($user)->get('/admin/analytics')->assertOk();
});

it('links a still-existing phone to its admin edit page, but not a deleted one', function () {
    $existing = makeRecommendablePhone(['name' => 'Still Here Phone']);

    bindFakeAnalytics([
        '|' => [],
        'date|' => [],
        'customEvent:phone_id,customEvent:phone_name,customEvent:brand|view_phone' => [
            ['customEvent:phone_id' => (string) $existing->id, 'customEvent:phone_name' => 'Still Here Phone', 'customEvent:brand' => 'Brand', 'eventCount' => '10'],
            ['customEvent:phone_id' => '999999', 'customEvent:phone_name' => 'Deleted Phone', 'customEvent:brand' => 'Brand', 'eventCount' => '5'],
        ],
    ]);
    $user = phoneDataUser(['dashboard-view']);

    $response = $this->actingAs($user)->get('/admin/analytics');

    $response->assertOk();
    $response->assertSee(route('phones.edit', $existing->id), false);
    // The deleted phone's name still shows, just without a link.
    $response->assertSee('Deleted Phone');
});

it('shows a clean empty state, not an error, when GA4 genuinely has no data for the period', function () {
    bindFakeAnalytics([
        '|' => [['activeUsers' => '0', 'newUsers' => '0', 'sessions' => '0', 'screenPageViews' => '0', 'engagementRate' => '0']],
    ]);
    $user = phoneDataUser(['dashboard-view']);

    $response = $this->actingAs($user)->get('/admin/analytics');

    $response->assertOk();
    $response->assertSee('No analytics data available for this period.');
    $response->assertDontSee('Analytics data temporarily unavailable.');
});

it('falls back cleanly, without a stack trace, when the GA4 API call fails', function () {
    bindFakeAnalytics([
        '|' => new RuntimeException('simulated GA4 outage - invalid credentials'),
    ]);
    $user = phoneDataUser(['dashboard-view']);

    $response = $this->actingAs($user)->get('/admin/analytics');

    $response->assertOk();
    $response->assertSee('Analytics data temporarily unavailable.');
    $response->assertDontSee('simulated GA4 outage', false);
    $response->assertDontSee('RuntimeException', false);
    $response->assertDontSee('Stack trace', false);
});

it('renders cleanly, degraded but not broken, when analytics is completely unconfigured', function () {
    // No binding at all - AnalyticsDashboardService resolves the real
    // GA4AnalyticsReportClient::fromConfig(), which returns its
    // "not configured" state whenever GA4_PROPERTY_ID/GA4_CREDENTIALS_PATH
    // aren't set (true in the test environment) rather than attempting a
    // real network call.
    $user = phoneDataUser(['dashboard-view']);

    $response = $this->actingAs($user)->get('/admin/analytics');

    $response->assertOk();
    $response->assertSee('Analytics data temporarily unavailable.');
});

it('supports today/7-day/30-day ranges and requests the matching GA4 date range', function () {
    $fake = bindFakeAnalytics(fullAnalyticsResponses());
    $user = phoneDataUser(['dashboard-view']);

    $this->actingAs($user)->get('/admin/analytics?range=today')->assertOk();
    $todayCall = collect($fake->calls)->first(fn ($call) => $call['dimensions'] === []);
    expect($todayCall['startDate'])->toBe('today')->and($todayCall['endDate'])->toBe('today');

    $fake->calls = [];
    $this->actingAs($user)->get('/admin/analytics?range=30d')->assertOk();
    $thirtyDayCall = collect($fake->calls)->first(fn ($call) => $call['dimensions'] === []);
    expect($thirtyDayCall['startDate'])->toBe('30daysAgo');

    $fake->calls = [];
    $this->actingAs($user)->get('/admin/analytics')->assertOk(); // default, no ?range
    $defaultCall = collect($fake->calls)->first(fn ($call) => $call['dimensions'] === []);
    expect($defaultCall['startDate'])->toBe('7daysAgo');
});

it('highlights the active date-range button', function () {
    bindFakeAnalytics(fullAnalyticsResponses());
    $user = phoneDataUser(['dashboard-view']);

    $response = $this->actingAs($user)->get('/admin/analytics?range=today');

    $response->assertOk();
    $todayHref = route('analytics.index', ['range' => 'today']);
    $sevenDayHref = route('analytics.index', ['range' => '7d']);

    // "Today"'s link is followed (allowing for whitespace/attribute order)
    // by btn-primary (active), while "Last 7 Days"'s is not.
    expect($response->getContent())
        ->toMatch('/href="'.preg_quote($todayHref, '/').'"\s+class="btn btn-primary"/')
        ->toMatch('/href="'.preg_quote($sevenDayHref, '/').'"\s+class="btn btn-outline-primary"/');
});

it('caches a GA4 report instead of calling the API again within the TTL, per date range', function () {
    $fake = bindFakeAnalytics(fullAnalyticsResponses());
    $user = phoneDataUser(['dashboard-view']);

    $this->actingAs($user)->get('/admin/analytics?range=7d')->assertOk();
    $callsAfterFirstLoad = count($fake->calls);
    expect($callsAfterFirstLoad)->toBeGreaterThan(0);

    $this->actingAs($user)->get('/admin/analytics?range=7d')->assertOk();
    expect(count($fake->calls))->toBe($callsAfterFirstLoad); // no new calls - served from cache

    // A different date range is a different cache key, so it DOES call again.
    $this->actingAs($user)->get('/admin/analytics?range=30d')->assertOk();
    expect(count($fake->calls))->toBeGreaterThan($callsAfterFirstLoad);
});

it('never sends GA4 property/credential configuration to the frontend', function () {
    $viteEnv = file_get_contents(base_path('.env.example'));

    // GA4_PROPERTY_ID / GA4_CREDENTIALS_PATH must never be VITE_-prefixed
    // (only VITE_-prefixed vars are exposed to the Vue/Vite frontend at all).
    expect($viteEnv)->not->toContain('VITE_GA4_PROPERTY_ID')
        ->not->toContain('VITE_GA4_CREDENTIALS_PATH');

    foreach (['js/app.js', 'js/utils/analytics.js'] as $file) {
        $source = file_get_contents(resource_path($file));
        expect($source)->not->toContain('GA4_PROPERTY_ID')
            ->not->toContain('GA4_CREDENTIALS_PATH')
            ->not->toContain('service_account')
            ->not->toContain('private_key');
    }
});

it('never renders the service account credentials path or raw exception details in the page HTML', function () {
    bindFakeAnalytics([
        '|' => new RuntimeException('/secret/path/service-account.json could not be read'),
    ]);
    $user = phoneDataUser(['dashboard-view']);

    $response = $this->actingAs($user)->get('/admin/analytics');

    $response->assertOk();
    $response->assertDontSee('service-account.json', false);
    $response->assertDontSee('/secret/path', false);
});

it('resolves the GA4 report client from the container as the AnalyticsReportClient interface, not a concrete class', function () {
    expect(app()->bound(AnalyticsReportClient::class))->toBeTrue();
});
