<?php

use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The main /admin dashboard now carries only a small Analytics summary
 * card + a "View Analytics ->" link to the dedicated /admin/analytics page
 * (App\Http\Controllers\AnalyticsController, see AnalyticsDashboardTest.php)
 * - these tests confirm the big breakdown sections are gone from here and
 * the summary card degrades the same way the full page does.
 */
it('shows a small Analytics summary card with a link to the dedicated analytics page', function () {
    bindFakeAnalytics(fullAnalyticsResponses());
    $user = phoneDataUser(['dashboard-view', 'phone-view']);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
    $response->assertViewIs('dashboard');
    $response->assertSee('Analytics');
    $response->assertSee('View Analytics');
    $response->assertSee(route('analytics.index'), false);

    // Summary numbers/top items are present...
    $response->assertSee('120'); // users
    $response->assertSee('iPhone 16 Pro'); // top viewed phone
});

it('no longer contains the full analytics breakdown sections on the main dashboard', function () {
    bindFakeAnalytics(fullAnalyticsResponses());
    $user = phoneDataUser(['dashboard-view', 'phone-view']);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
    $response->assertDontSee('Most Viewed Phones');
    $response->assertDontSee('Most Popular Budget Ranges');
    $response->assertDontSee('Most Viewed Brands');
    $response->assertDontSee('Top Searches');
    $response->assertDontSee('Most Compared Phones');
    $response->assertDontSee('Find My Phone');
    $response->assertDontSee('Visitors');
});

it('falls back cleanly on the dashboard summary card when the GA4 API call fails', function () {
    bindFakeAnalytics([
        '|' => new RuntimeException('simulated GA4 outage'),
    ]);
    $user = phoneDataUser(['dashboard-view', 'phone-view']);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
    $response->assertSee('Catalogue Overview'); // rest of the dashboard is unaffected
    $response->assertSee('Analytics data temporarily unavailable.');
    $response->assertDontSee('simulated GA4 outage', false);
});

it('still shows the dashboard summary card when analytics is completely unconfigured', function () {
    $user = phoneDataUser(['dashboard-view', 'phone-view']);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
    $response->assertSee('Analytics data temporarily unavailable.');
});

it('lists Analytics in the admin sidebar, linking to the dedicated page, for a user with dashboard-view', function () {
    $this->seed(MenuSeeder::class);
    bindFakeAnalytics(fullAnalyticsResponses());
    $user = phoneDataUser(['dashboard-view', 'phone-view']);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
    $response->assertSee(route('analytics.index'), false);
});

it('hides the Analytics sidebar link from a user without dashboard-view', function () {
    $this->seed(MenuSeeder::class);
    $user = phoneDataUser(['phone-view']); // no dashboard-view

    // Any authenticated, permitted page renders the sidebar - phone-view
    // is enough to reach the phone-data dashboard without dashboard-view.
    $response = $this->actingAs($user)->get('/phone-data');

    $response->assertOk();
    $response->assertDontSee(route('analytics.index'), false);
});
