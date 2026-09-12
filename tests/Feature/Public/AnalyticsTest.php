<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

/*
 * GA4 analytics is entirely client-side (resources/js/utils/analytics.js,
 * wired from resources/js/app.js and a handful of Public Vue
 * components/pages) - there is no PHP business logic to exercise. These
 * tests verify the wiring statically (the right calls exist, in the
 * right files, with no PII) and, for the one piece of real logic (the
 * budget bracket → GA4 range taxonomy), by actually running the JS
 * module through Node - the same runtime `npm run build` already
 * requires, so this adds no new dependency.
 */

function analyticsSource(string $relativePath): string
{
    return file_get_contents(resource_path($relativePath));
}

it('reads the GA4 measurement ID from the environment, never hardcoded', function () {
    $analytics = analyticsSource('js/utils/analytics.js');

    expect($analytics)->toContain('import.meta.env.VITE_GA_MEASUREMENT_ID');
    // No literal GA4-style ID ("G-XXXXXXX") baked into the module itself.
    expect(preg_match('/[\'"]G-[A-Z0-9]+[\'"]/', $analytics))->toBe(0);
});

it('documents the GA4 measurement ID variable in .env.example', function () {
    $envExample = file_get_contents(base_path('.env.example'));

    expect($envExample)->toContain('VITE_GA_MEASUREMENT_ID=');
});

it('only initialises analytics for a production build with a measurement ID set', function () {
    $analytics = analyticsSource('js/utils/analytics.js');

    expect($analytics)->toContain('import.meta.env.PROD');
});

it('never loads the public analytics bundle on the admin login page', function () {
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $publicAppBundle = $manifest['resources/js/app.js']['file'];

    $response = $this->get('/login');

    $response->assertOk();
    $response->assertDontSee($publicAppBundle, false);
    $response->assertDontSee('gtag', false);
    $response->assertDontSee('googletagmanager', false);
});

it('loads the public analytics bundle on the public site', function () {
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $publicAppBundle = $manifest['resources/js/app.js']['file'];

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee($publicAppBundle, false);
});

it('wires GA4 pageviews to real Inertia navigation, not the automatic gtag pageview', function () {
    $appJs = analyticsSource('js/app.js');

    expect($appJs)->toContain("router.on('navigate'")
        ->toContain('trackPageview');

    $analytics = analyticsSource('js/utils/analytics.js');
    expect($analytics)->toContain('send_page_view: false');
});

it('generates the required catalogue, search, and brand events from the right components', function () {
    expect(analyticsSource('js/Pages/Public/Phones/Show.vue'))->toContain("trackEvent('view_phone'");
    expect(analyticsSource('js/Pages/Public/Phones/Index.vue'))
        ->toContain("trackEvent('budget_selected'")
        ->toContain("trackEvent('filter_applied'")
        ->toContain("trackEvent('sort_changed'");
    expect(analyticsSource('js/Pages/Public/Phones/Brand.vue'))->toContain("trackEvent('brand_viewed'");
    expect(analyticsSource('js/Components/Public/HeaderSearch.vue'))->toContain("trackEvent('search'");
});

it('generates compare events with a phone count, not just a boolean', function () {
    $compare = analyticsSource('js/Pages/Public/Compare/Index.vue');

    expect($compare)
        ->toContain("trackEvent('compare_started'")
        ->toContain("trackEvent('compare_phones'")
        ->toContain('number_of_phones');
});

it('generates the full Find My Phone funnel', function () {
    $form = analyticsSource('js/Components/Public/FindMyPhoneForm.vue');

    expect($form)
        ->toContain("trackEvent('find_phone_started')")
        ->toContain("trackEvent('find_phone_step_completed'")
        ->toContain('step_number')
        ->toContain('step_name')
        ->toContain("trackEvent('find_phone_completed'");

    $results = analyticsSource('js/Pages/Public/Results.vue');
    expect($results)->toContain("trackEvent('recommendation_viewed'");

    $resultCard = analyticsSource('js/Components/Public/ResultCard.vue');
    expect($resultCard)->toContain("trackEvent('recommendation_phone_clicked'");
});

it('never sends personal or identifying data in the Find My Phone completion event', function () {
    $form = analyticsSource('js/Components/Public/FindMyPhoneForm.vue');

    // Isolate just the find_phone_completed payload, not the whole file,
    // so this can't accidentally pass by finding an unrelated `name` or
    // `email` elsewhere in the component.
    preg_match("/trackEvent\('find_phone_completed',\s*\{(.*?)\}\);/s", $form, $matches);
    expect($matches)->not->toBeEmpty('find_phone_completed call not found');
    $payload = $matches[1];

    foreach (['email', 'phone_number', 'address', 'password', 'auth', 'name:'] as $piiField) {
        expect($payload)->not->toContain($piiField);
    }

    // Must never spread the raw questionnaire form object wholesale.
    expect($payload)->not->toContain('...form');

    // Only the intended, aggregate-safe fields are present.
    expect($payload)
        ->toContain('budget_range')
        ->toContain('usage_category')
        ->toContain('preferred_brands')
        ->toContain('required_5g')
        ->toContain('required_nfc')
        ->toContain('storage_requirement');
});

it('buckets a budget amount into the same ranges as the existing "Under X" price brackets, matching config', function () {
    $brackets = json_encode(config('phone_kinbo.price_brackets'));
    $modulePath = resource_path('js/utils/budget.js');

    $script = <<<JS
        import { budgetRangeFor } from '{$modulePath}';
        const brackets = {$brackets};
        console.log(JSON.stringify({
            underFirstBracket: budgetRangeFor(12000, brackets),
            betweenBrackets: budgetRangeFor(27000, brackets),
            topBracket: budgetRangeFor(500000, brackets),
            noBudget: budgetRangeFor(null, brackets),
        }));
        JS;

    $result = Process::run(['node', '--input-type=module', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    $ranges = json_decode($result->output(), true);

    expect($ranges['underFirstBracket'])->toBe(['budget_min' => 0, 'budget_max' => 15000, 'budget_range' => '0-15000']);
    expect($ranges['betweenBrackets'])->toBe(['budget_min' => 20000, 'budget_max' => 30000, 'budget_range' => '20000-30000']);
    expect($ranges['topBracket'])->toBe(['budget_min' => 100000, 'budget_max' => null, 'budget_range' => '100000+']);
    expect($ranges['noBudget'])->toBe(['budget_min' => null, 'budget_max' => null, 'budget_range' => 'any']);
});

it('sanitizes event params to scalars only, dropping anything that is not a safe primitive', function () {
    $analytics = analyticsSource('js/utils/analytics.js');

    expect($analytics)->toContain('function sanitize');
    expect($analytics)->toContain("typeof value === 'string'");
});
