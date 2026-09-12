<?php

use App\Models\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The exact URL list from the "prepare for production launch" smoke test
 * requirement - one status-code check per critical public route plus
 * auth protection on the admin entry points. Deliberately end-to-end
 * (real HTTP requests through the full middleware/exception stack, not
 * unit calls) since that's what a real deploy needs confidence in.
 */
it('serves every production-critical URL with the expected status', function () {
    $phone = makeRecommendablePhone(priceAttrs: ['amount' => 20000]);
    $brand = $phone->brand;

    $expectations = [
        '/' => 200,
        '/phones' => 200,
        "/phones/brand/{$brand->slug}" => 200,
        "/phones/{$phone->slug}" => 200,
        '/compare' => 200,
        '/about' => 200,
        '/best-phones-under-15000' => 200,
        '/best-phones-under-25000' => 200,
        '/best-phones-under-40000' => 200,
        '/best-gaming-phones-under-25000' => 200,
        '/best-camera-phones-under-30000' => 200,
        '/sitemap.xml' => 200,
        '/robots.txt' => 200,
        '/login' => 200,
        '/admin' => 302, // unauthenticated -> redirected to login, never exposed
        '/phones/does-not-exist' => 404,
        '/phones/brand/does-not-exist' => 404,
    ];

    foreach ($expectations as $url => $status) {
        $this->get($url)->assertStatus($status);
    }
});

it('never leaks debug information on a public 404, regardless of APP_DEBUG', function () {
    $response = $this->get('/phones/does-not-exist');

    $response->assertDontSee('/Users/', escape: false)
        ->assertDontSee('vendor/laravel', escape: false)
        ->assertDontSee('Stack trace', escape: false);
});

it('gives the sitemap and robots.txt the correct content types', function () {
    $this->get('/sitemap.xml')->assertHeader('Content-Type', 'application/xml');
    $this->get('/robots.txt')->assertHeader('Content-Type', 'text/plain; charset=utf-8');
});

it('reflects real, current phone counts on the homepage rather than a stale/fabricated number', function () {
    Phone::query()->delete();
    makeRecommendablePhone();
    makeRecommendablePhone();
    makeRecommendablePhone();

    $this->get('/')->assertInertia(fn ($page) => $page->where('stats.phone_count', 3));
});
