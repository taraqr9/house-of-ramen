<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders an on-brand public Inertia error page for a completely unmatched top-level path', function () {
    $response = $this->get('/this-was-never-a-registered-page');

    $response->assertNotFound()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Error')
        ->where('status', 404)
    );
});

it('never indexes the public error page', function () {
    $response = $this->get('/this-was-never-a-registered-page');

    // The error page has no controller-built `seo` prop (it's rendered
    // from the exception handler, not a normal request) - noindex is
    // baked into the Vue page itself instead (see Pages/Public/Error.vue).
    $response->assertInertia(fn (Assert $page) => $page->component('Public/Error'));
});

it('leaves admin/auth area 404s on the admin theme, never the public Inertia error page', function () {
    $response = $this->get('/restaurant/this-does-not-exist');

    $response->assertNotFound();
    expect($response->headers->get('X-Inertia'))->toBeNull();
});
