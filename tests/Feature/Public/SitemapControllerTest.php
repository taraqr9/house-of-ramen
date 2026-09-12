<?php

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves a valid XML sitemap including the homepage, active phones, and active brands', function () {
    $brand = Brand::factory()->create(['slug' => 'samsung', 'is_active' => true]);
    $phone = makeRecommendablePhone(['brand_id' => $brand->id]);
    $inactivePhone = makeRecommendablePhone(['is_active' => false]);

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
        ->toContain(config('seo.base_url').'/phones')
        ->toContain(config('seo.base_url').'/about')
        ->toContain(config('seo.base_url')."/phones/{$phone->slug}")
        ->toContain(config('seo.base_url').'/phones/brand/samsung')
        ->not->toContain(config('seo.base_url')."/phones/{$inactivePhone->slug}");
});

it('never includes non-indexable URLs in the sitemap', function () {
    makeRecommendablePhone();

    $response = $this->get('/sitemap.xml');

    $xml = simplexml_load_string($response->getContent());
    $locs = collect(iterator_to_array($xml->url, false))->map(fn ($url) => (string) $url->loc)->all();

    foreach ($locs as $loc) {
        expect($loc)->not->toContain('/compare')
            ->not->toContain('/find-my-phone/results')
            ->not->toContain('?');
    }
});
