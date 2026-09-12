<?php

use App\Enums\SourceTypeEnum;
use App\Models\PhoneSource;
use App\Services\PhoneImport\ConfidenceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scores a manufacturer source with complete data highly', function () {
    $source = PhoneSource::factory()->create(['type' => SourceTypeEnum::MANUFACTURER, 'reliability_score' => 95]);

    $score = (new ConfidenceCalculator)->categoryScore($source, 1.0);

    expect($score)->toBeGreaterThanOrEqual(95);
});

it('penalizes an unreliable source even with complete data', function () {
    $source = PhoneSource::factory()->create(['type' => SourceTypeEnum::AI_ASSISTED, 'reliability_score' => 45]);

    $score = (new ConfidenceCalculator)->categoryScore($source, 1.0);

    expect($score)->toBeLessThan(80);
});

it('penalizes incomplete data even from a reliable source', function () {
    $source = PhoneSource::factory()->create(['type' => SourceTypeEnum::MANUFACTURER, 'reliability_score' => 95]);

    $complete = (new ConfidenceCalculator)->categoryScore($source, 1.0);
    $incomplete = (new ConfidenceCalculator)->categoryScore($source, 0.2);

    expect($incomplete)->toBeLessThan($complete);
});

it('computes completeness as the share of present expected fields', function () {
    $calculator = new ConfidenceCalculator;

    $ratio = $calculator->completeness(
        ['brand' => 'Samsung', 'model' => 'Galaxy S24', 'status' => null],
        ['brand', 'model', 'status']
    );

    expect($ratio)->toBe(2 / 3);
});

it('auto-approves only when the overall score clears the configured threshold', function () {
    $calculator = new ConfidenceCalculator;
    $threshold = config('phone_confidence.auto_approve_threshold');

    expect($calculator->shouldAutoApprove($threshold))->toBeTrue()
        ->and($calculator->shouldAutoApprove($threshold - 1))->toBeFalse();
});
