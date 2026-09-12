<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the About Us page with founder details and a real, publicly reachable photo URL', function () {
    $response = $this->get('/about');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/About')
        ->where('founderPhotoUrl', fn ($url) => str_contains($url, '/images/about/founder-taraq-rahman.jpg'))
        ->has('seo')
    );
});

it('gives the About page real, indexable SEO metadata', function () {
    $response = $this->get('/about');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.title', 'About Us')
        ->where('seo.robots', 'index,follow')
        ->where('seo.canonical', config('seo.base_url').'/about')
        ->where('seo.description', fn ($description) => str_contains($description, 'Phone Kinbo') && str_contains($description, 'Bangladesh'))
    );
});

it('ships the founder photo file itself, ready to be served as a static asset', function () {
    // Static files under public/ are served directly by the webserver (or
    // the `php artisan serve` dev router), never routed through Laravel -
    // so this checks the file is actually present in the deployed build
    // rather than making an HTTP request Laravel's test client can't serve.
    $path = public_path('images/about/founder-taraq-rahman.jpg');

    expect(is_file($path))->toBeTrue("Founder photo missing at {$path}");
    expect(getimagesize($path))->not->toBeFalse('Founder photo is not a valid image');
});

it('links to About from the public site navigation', function () {
    // Nav/footer links render client-side via Vue (PublicLayout.vue) and
    // only appear in the raw server HTML when SSR is actually running -
    // not guaranteed in this test environment - so this checks the
    // component source directly rather than the HTTP response body.
    $layout = file_get_contents(resource_path('js/Layouts/PublicLayout.vue'));

    expect($layout)->toContain("{ label: 'About', href: '/about' }")
        ->toContain('href="/about"');
});

it('never exposes the About route to the seo-landing catch-all - it is a real, dedicated page', function () {
    $response = $this->get('/about');

    $response->assertInertia(fn (Assert $page) => $page->component('Public/About'));
});
