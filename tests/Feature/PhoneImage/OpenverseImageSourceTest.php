<?php

use App\Models\Brand;
use App\Models\Phone;
use App\Services\PhoneImage\Sources\OpenverseImageSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeOpenverseResult(array $overrides = []): array
{
    return array_merge([
        'title' => 'Samsung Galaxy S24 Ultra',
        'url' => 'https://upload.wikimedia.org/wikipedia/commons/samsung-s24-ultra.jpg',
        'foreign_landing_url' => 'https://commons.wikimedia.org/wiki/File:Samsung_Galaxy_S24_Ultra.jpg',
        'width' => 1000,
        'height' => 800,
        'filetype' => 'jpg',
        'license' => 'by-sa',
        'license_version' => '4.0',
        'attribution' => '"Samsung Galaxy S24 Ultra" by Jane Doe is licensed under CC BY-SA 4.0.',
        'tags' => [['name' => 'samsung galaxy s24 ultra']],
    ], $overrides);
}

it('phrase-matches a real Openverse result and passes through its own attribution/license fields verbatim', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);

    Http::fake(['api.openverse.org/*' => Http::response(['results' => [fakeOpenverseResult()]], 200)]);

    $source = new OpenverseImageSource('openverse', ['user_agent' => 'TestBot/1.0']);
    $results = $source->search($phone);

    expect($results)->toHaveCount(1)
        ->and($results[0]['confidence'])->toBeGreaterThanOrEqual(65)
        ->and($results[0]['license'])->toBe('BY-SA 4.0')
        ->and($results[0]['attribution'])->toBe('"Samsung Galaxy S24 Ultra" by Jane Doe is licensed under CC BY-SA 4.0.')
        ->and($results[0]['source_url'])->toBe('https://commons.wikimedia.org/wiki/File:Samsung_Galaxy_S24_Ultra.jpg');
});

it('caps confidence when Openverse returns no license, even on a perfect title match', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);

    Http::fake(['api.openverse.org/*' => Http::response(['results' => [
        fakeOpenverseResult(['license' => null, 'license_version' => null, 'attribution' => null]),
    ]], 200)]);

    $source = new OpenverseImageSource('openverse', ['user_agent' => 'TestBot/1.0']);
    $results = $source->search($phone);

    expect($results[0]['confidence'])->toBeLessThanOrEqual(40)
        ->and($results[0]['license'])->toBeNull();
});

it('rejects an obvious non-photo result the same way every other source does', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Samsung']), 'brand')
        ->create(['name' => 'Galaxy S24 Ultra']);

    Http::fake(['api.openverse.org/*' => Http::response(['results' => [
        fakeOpenverseResult(['title' => 'Samsung Galaxy S24 Ultra retail box']),
    ]], 200)]);

    $source = new OpenverseImageSource('openverse', ['user_agent' => 'TestBot/1.0']);
    $results = $source->search($phone);

    expect($results)->toBeEmpty();
});

it('does not phrase-match a title naming a different, longer sibling model glued on with no separator', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Vivo']), 'brand')
        ->create(['name' => 'Y20']);

    // "Y20s" is a distinct catalogued model from "Y20" - the "s" is
    // concatenated directly onto the phrase with no space/underscore
    // separator, so a naive substring check treats "vivo y20" as found
    // inside "vivo y20s (g) rear" and would otherwise phrase-match at 95.
    Http::fake(['api.openverse.org/*' => Http::response(['results' => [
        fakeOpenverseResult([
            'title' => 'Vivo Y20s (G) rear',
            'tags' => [['name' => 'vivo y20s']],
        ]),
    ]], 200)]);

    $source = new OpenverseImageSource('openverse', ['user_agent' => 'TestBot/1.0']);
    $results = $source->search($phone);

    expect($results[0]['confidence'])->toBeLessThan(65);
});

it('does not double the brand in the query when the model name already includes it', function () {
    $phone = Phone::factory()->for(Brand::factory()->create(['name' => 'Redmi']), 'brand')
        ->create(['name' => 'Redmi Note 15 5G']);

    Http::fake(['api.openverse.org/*' => Http::response(['results' => [
        fakeOpenverseResult(['title' => 'REDMI Note 15 5G camera island']),
    ]], 200)]);

    $source = new OpenverseImageSource('openverse', ['user_agent' => 'TestBot/1.0']);
    $results = $source->search($phone);

    expect($results[0]['confidence'])->toBeGreaterThanOrEqual(65);

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'q=Redmi+Note+15+5G')
            || str_contains(urldecode((string) $request->url()), 'q=Redmi Note 15 5G');
    });
});
