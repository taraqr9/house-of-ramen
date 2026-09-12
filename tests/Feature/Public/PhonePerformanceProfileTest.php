<?php

use App\Models\Phone;
use App\Models\PhoneMarketPrice;
use App\Models\PhoneSpec;
use App\Models\PhoneVariant;
use App\Services\Presentation\PhonePerformanceProfile;
use App\Services\Recommendation\PhoneCandidate;
use App\Services\Recommendation\PhoneScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('scores every one of the 8 recommendation-engine dimensions, and only those 8', function () {
    $phone = Phone::factory()->create(['category' => 'flagship']);
    PhoneSpec::factory()->create(['phone_id' => $phone->id, 'processor' => 'Snapdragon 8 Gen 3']);
    $phone->load('variants');
    PhoneVariant::factory()->create(['phone_id' => $phone->id]);

    $profile = PhonePerformanceProfile::for($phone->fresh(['spec', 'variants']));

    expect($profile)->not->toBeNull()
        ->and(collect($profile)->pluck('key')->all())
        ->toBe(['performance', 'gaming', 'camera', 'battery', 'display', 'software', 'build', 'charging'])
        ->and(collect($profile)->pluck('key'))->not->toContain('value');
});

it('produces scores identical to calling PhoneScorer directly - never a second, drifting formula', function () {
    $phone = Phone::factory()->create(['category' => 'midrange']);
    PhoneSpec::factory()->create(['phone_id' => $phone->id, 'processor' => 'Snapdragon 7 Gen 3', 'battery_capacity_mah' => 5000]);
    $variant = PhoneVariant::factory()->create(['phone_id' => $phone->id]);
    $phone = $phone->fresh(['spec', 'variants']);

    $profile = PhonePerformanceProfile::for($phone);
    $direct = app(PhoneScorer::class)->performance(new PhoneCandidate(
        $phone, $variant, new PhoneMarketPrice(['price_type' => 'official_bd', 'price' => 0]),
    ));

    expect(collect($profile)->firstWhere('key', 'performance')['score'])->toBe($direct);
});

it('is deterministic - scoring the same phone twice gives identical results', function () {
    $phone = Phone::factory()->create();
    PhoneSpec::factory()->create(['phone_id' => $phone->id]);
    PhoneVariant::factory()->create(['phone_id' => $phone->id]);
    $phone = $phone->fresh(['spec', 'variants']);

    expect(PhonePerformanceProfile::for($phone))->toBe(PhonePerformanceProfile::for($phone));
});

it('converts a 0-100 score to a half-star-resolution /5 rating', function () {
    $phone = Phone::factory()->create(['category' => 'flagship']);
    PhoneSpec::factory()->create(['phone_id' => $phone->id, 'processor' => 'Apple A17 Pro']); // scores 97
    PhoneVariant::factory()->create(['phone_id' => $phone->id]);
    $phone = $phone->fresh(['spec', 'variants']);

    $performance = collect(PhonePerformanceProfile::for($phone))->firstWhere('key', 'performance');

    expect($performance['stars'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(5)
        ->and($performance['stars'] * 2)->toEqual((int) ($performance['stars'] * 2)); // always a multiple of 0.5
});

it('returns null - not a fabricated score - for a phone with no variant at all', function () {
    $phone = Phone::factory()->create();
    PhoneSpec::factory()->create(['phone_id' => $phone->id]);
    $phone = $phone->fresh(['spec', 'variants']);

    expect(PhonePerformanceProfile::for($phone))->toBeNull();
});

it('still scores a phone with no spec row at all, using the same honest category/default baselines the live recommendation engine uses', function () {
    $phone = Phone::factory()->create(['category' => 'budget']);
    PhoneVariant::factory()->create(['phone_id' => $phone->id]);
    $phone = $phone->fresh(['spec', 'variants']);

    $profile = PhonePerformanceProfile::for($phone);

    expect($profile)->not->toBeNull()->and(count($profile))->toBe(8);
    foreach ($profile as $dimension) {
        expect($dimension['score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    }
});

it('renders the phone statistics profile on the public detail page', function () {
    $phone = makeRecommendablePhone(['name' => 'Stats Phone'], priceAttrs: ['amount' => 20000]);

    $response = $this->get("/phones/{$phone->slug}");

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Phones/Show')
        ->has('performanceProfile', 8)
    );
});
