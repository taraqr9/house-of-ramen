<?php

it('serves a robots.txt that disallows admin/auth areas and points to the sitemap', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    $body = $response->getContent();

    expect($body)->toContain('User-agent: *')
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /login')
        ->toContain('Disallow: /restaurant')
        ->toContain('Sitemap: '.config('seo.base_url').'/sitemap.xml');
});

it('never blocks crawling of the public site itself', function () {
    $response = $this->get('/robots.txt');

    $disallowedPaths = collect(explode("\n", $response->getContent()))
        ->filter(fn ($line) => str_starts_with($line, 'Disallow:'))
        ->map(fn ($line) => trim(str_replace('Disallow:', '', $line)))
        ->all();

    expect($disallowedPaths)->not->toContain('/')
        ->not->toContain('/menu')
        ->not->toContain('/gallery')
        ->not->toContain('/about')
        ->not->toContain('/contact');
});
