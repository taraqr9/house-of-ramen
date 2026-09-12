<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the About page with the restaurant\'s real name and description', function () {
    makeRestaurant(['name' => 'House of Ramen', 'description' => 'Our real story.']);

    $response = $this->get('/about');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/About')
        ->where('restaurant.name', 'House of Ramen')
        ->where('restaurant.description', 'Our real story.')
        ->has('seo')
    );
});

it('gives the About page real, indexable SEO metadata', function () {
    makeRestaurant();

    $response = $this->get('/about');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.title', 'About Us')
        ->where('seo.robots', 'index,follow')
        ->where('seo.canonical', config('seo.base_url').'/about')
    );
});

it('links to About from the public site navigation', function () {
    // Nav/footer links render client-side via Vue (PublicLayout.vue) and
    // only appear in the raw server HTML when SSR is actually running -
    // not guaranteed in this test environment - so this checks the
    // component source directly rather than the HTTP response body.
    $layout = file_get_contents(resource_path('js/Layouts/PublicLayout.vue'));

    expect($layout)->toContain("{ label: 'About', href: '/about' }");
});

it('never exposes the About route to a catch-all - it is a real, dedicated page', function () {
    makeRestaurant();

    $response = $this->get('/about');

    $response->assertInertia(fn (Assert $page) => $page->component('Public/About'));
});
