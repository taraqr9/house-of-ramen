<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * One status-code check per critical public route plus auth protection
 * on the admin entry point. Deliberately end-to-end (real HTTP requests
 * through the full middleware/exception stack, not unit calls) since
 * that's what a real deploy needs confidence in.
 */
it('serves every production-critical URL with the expected status', function () {
    makeRestaurant();

    $expectations = [
        '/' => 200,
        '/menu' => 200,
        '/gallery' => 200,
        '/about' => 200,
        '/contact' => 200,
        '/sitemap.xml' => 200,
        '/robots.txt' => 200,
        '/login' => 200,
        '/admin' => 302, // unauthenticated -> redirected to login, never exposed
        '/this-page-does-not-exist' => 404,
    ];

    foreach ($expectations as $url => $status) {
        $this->get($url)->assertStatus($status);
    }
});

it('never leaks debug information on a public 404, regardless of APP_DEBUG', function () {
    $response = $this->get('/this-page-does-not-exist');

    $response->assertDontSee('/Users/', escape: false)
        ->assertDontSee('vendor/laravel', escape: false)
        ->assertDontSee('Stack trace', escape: false);
});

it('gives the sitemap and robots.txt the correct content types', function () {
    $this->get('/sitemap.xml')->assertHeader('Content-Type', 'application/xml');
    $this->get('/robots.txt')->assertHeader('Content-Type', 'text/plain; charset=utf-8');
});
