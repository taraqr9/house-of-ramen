<?php

it('serves a valid XML sitemap including every public page', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()->assertHeader('Content-Type', 'application/xml');

    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse('sitemap is not well-formed XML');

    // Not collect($xml->url) - casting/iterating a SimpleXMLElement's
    // repeated-child accessor through Collection's Traversable handling
    // collapses same-named siblings down to the last one. iterator_to_array
    // with reindexing off is what actually walks every <url> node.
    $locs = collect(iterator_to_array($xml->url, false))->map(fn ($url) => (string) $url->loc)->all();

    expect($locs)->toContain(config('seo.base_url').'/')
        ->toContain(config('seo.base_url').'/menu')
        ->toContain(config('seo.base_url').'/gallery')
        ->toContain(config('seo.base_url').'/about')
        ->toContain(config('seo.base_url').'/contact');
});

it('never includes a query string in the sitemap', function () {
    $response = $this->get('/sitemap.xml');

    $xml = simplexml_load_string($response->getContent());
    $locs = collect(iterator_to_array($xml->url, false))->map(fn ($url) => (string) $url->loc)->all();

    foreach ($locs as $loc) {
        expect($loc)->not->toContain('?');
    }
});
