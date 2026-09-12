<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns ranked recommendations for a public request', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 25000]);
    makeRecommendablePhone(priceAttrs: ['amount' => 32000]);

    $response = $this->postJson(route('recommendations.create'), [
        'max_budget' => 40000,
        'importance' => ['gaming' => 4, 'camera' => 2],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'criteria' => ['max_budget', 'primary_usage', 'price_preference'],
            'count',
            'results' => [
                '*' => ['rank', 'phone_id', 'phone_name', 'match_score', 'price', 'score_breakdown', 'reasons', 'tradeoffs'],
            ],
        ]);

    expect($response->json('count'))->toBe(2);
});

it('requires no authentication to request a recommendation', function () {
    $response = $this->postJson(route('recommendations.create'), ['max_budget' => 20000]);

    $response->assertOk();
});

it('rejects an importance value outside the 1-5 scale', function () {
    $response = $this->postJson(route('recommendations.create'), [
        'importance' => ['gaming' => 9],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['importance.gaming']);
});

it('rejects an excluded brand id that does not exist', function () {
    $response = $this->postJson(route('recommendations.create'), [
        'excluded_brand_ids' => [999999],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['excluded_brand_ids.0']);
});

it('returns an empty result set as valid json rather than erroring when nothing matches', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 90000]);

    $response = $this->postJson(route('recommendations.create'), ['max_budget' => 5000]);

    $response->assertOk()->assertJson(['count' => 0, 'results' => []]);
});
