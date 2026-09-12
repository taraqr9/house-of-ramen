<?php

use App\Models\Brand;
use App\Models\PhoneVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds a phone by its own name', function () {
    makeRecommendablePhone(['name' => 'Galaxy A55'], priceAttrs: ['amount' => 42000]);

    $response = $this->getJson('/phones/search?q=A55');

    $response->assertOk()->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.name', 'Galaxy A55');
});

it('finds a phone by brand name alone', function () {
    $brand = Brand::factory()->create(['name' => 'Xiaomi']);
    makeRecommendablePhone(['brand_id' => $brand->id, 'name' => 'Redmi Note 13']);

    $response = $this->getJson('/phones/search?q=Xiaomi');

    $response->assertOk()->assertJsonCount(1, 'results');
});

it('finds a phone by a compound brand+model query where neither field alone contains the full phrase', function () {
    $brand = Brand::factory()->create(['name' => 'Samsung']);
    makeRecommendablePhone(['brand_id' => $brand->id, 'name' => 'Galaxy A55']);

    $response = $this->getJson('/phones/search?q=Samsung A55');

    $response->assertOk()->assertJsonCount(1, 'results');
});

it('finds a phone by model number', function () {
    makeRecommendablePhone(['name' => 'Galaxy A55', 'model_number' => 'SM-A556E']);

    $response = $this->getJson('/phones/search?q=SM-A556E');

    $response->assertOk()->assertJsonCount(1, 'results');
});

it('matches partial model names', function () {
    makeRecommendablePhone(['name' => 'iPhone 15 Pro Max']);

    $response = $this->getJson('/phones/search?q=iphone 15');

    $response->assertOk()->assertJsonCount(1, 'results');
});

it('returns an empty result set for a query that matches nothing', function () {
    makeRecommendablePhone(['name' => 'Galaxy A55']);

    $response = $this->getJson('/phones/search?q=zzznotarealphone');

    $response->assertOk()->assertJsonCount(0, 'results');
});

it('rejects a query shorter than the minimum character threshold', function () {
    $response = $this->getJson('/phones/search?q=a');

    $response->assertStatus(422);
});

it('requires a query parameter at all', function () {
    $response = $this->getJson('/phones/search');

    $response->assertStatus(422);
});

it('never returns an inactive phone', function () {
    makeRecommendablePhone(['name' => 'Discontinued Phone', 'is_active' => false]);

    $response = $this->getJson('/phones/search?q=Discontinued');

    $response->assertOk()->assertJsonCount(0, 'results');
});

it('returns each matching phone once even when it has multiple variants', function () {
    $phone = makeRecommendablePhone(['name' => 'Multi Variant Phone'], priceAttrs: ['amount' => 20000]);
    PhoneVariant::factory()->create(['phone_id' => $phone->id, 'storage_gb' => 256]);

    $response = $this->getJson('/phones/search?q=Multi Variant');

    $response->assertOk()->assertJsonCount(1, 'results');
});

it('includes the cheapest current market price and image in each result', function () {
    makeRecommendablePhone(['name' => 'Galaxy A55'], priceAttrs: ['amount' => 42000]);

    $response = $this->getJson('/phones/search?q=Galaxy A55');

    $response->assertOk()
        ->assertJsonPath('results.0.price', 42000)
        ->assertJsonStructure(['results' => [['slug', 'name', 'brand', 'price', 'image_url']]]);
});

it('caps results at a reasonable limit', function () {
    for ($i = 1; $i <= 12; $i++) {
        makeRecommendablePhone(['name' => "Test Phone {$i}"]);
    }

    $response = $this->getJson('/phones/search?q=Test Phone');

    $response->assertOk();
    expect(count($response->json('results')))->toBeLessThanOrEqual(8);
});
