<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('submits the questionnaire straight to the real recommendation engine and renders real results', function () {
    $withinBudget = makeRecommendablePhone(priceAttrs: ['amount' => 20000]);
    makeRecommendablePhone(priceAttrs: ['amount' => 90000]);

    $response = $this->post('/find-my-phone/results', [
        'max_budget' => 30000,
        'importance' => ['camera' => 5],
    ]);

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Results')
        ->has('results', 1)
        ->where('results.0.phone_id', $withinBudget->id)
        ->has('results.0.reasons')
        ->has('results.0.phone_slug')
        ->where('criteria.max_budget', 30000)
    );
});

it('rejects a malformed questionnaire submission with a validation error instead of guessing', function () {
    $response = $this->post('/find-my-phone/results', [
        'importance' => ['camera' => 9], // out of the 1-5 range
    ]);

    $response->assertSessionHasErrors('importance.camera');
});

it('accepts a custom budget amount that does not match any quick bracket', function () {
    $withinCustomBudget = makeRecommendablePhone(priceAttrs: ['amount' => 17500]);
    makeRecommendablePhone(priceAttrs: ['amount' => 25000]);

    $response = $this->post('/find-my-phone/results', ['max_budget' => 18000]);

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('criteria.max_budget', 18000)
        ->has('results', 1)
        ->where('results.0.phone_id', $withinCustomBudget->id)
    );
});

it('rejects a negative budget instead of silently clamping or ignoring it', function () {
    $response = $this->post('/find-my-phone/results', ['max_budget' => -5000]);

    $response->assertSessionHasErrors('max_budget');
});

it('rejects a budget below the minimum meaningful amount', function () {
    $response = $this->post('/find-my-phone/results', ['max_budget' => 100]);

    $response->assertSessionHasErrors('max_budget');
});

it('rejects a non-numeric budget value', function () {
    $response = $this->post('/find-my-phone/results', ['max_budget' => 'not-a-number']);

    $response->assertSessionHasErrors('max_budget');
});

it('sends a direct GET on the results URL back to the homepage questionnaire', function () {
    $response = $this->get('/find-my-phone/results');

    $response->assertRedirect('/#find-my-phone');
});

it('noindexes the results page - it only ever exists as a POST response, never a crawlable GET URL with content', function () {
    makeRecommendablePhone(priceAttrs: ['amount' => 20000]);

    $response = $this->post('/find-my-phone/results', ['max_budget' => 30000]);

    $response->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,follow'));
});
