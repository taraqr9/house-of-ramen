<?php

use App\Enums\SourceTypeEnum;
use App\Models\PhoneSource;
use App\Services\PhoneImport\ConflictDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports no conflict when values agree', function () {
    $existingSource = PhoneSource::factory()->create(['reliability_score' => 80]);
    $newSource = PhoneSource::factory()->create(['reliability_score' => 80]);

    $result = (new ConflictDetector)->evaluate(5000, $existingSource, 5000, $newSource);

    expect($result)->toBeNull();
});

it('flags disagreeing values for review when the new source is not meaningfully more reliable', function () {
    $existingSource = PhoneSource::factory()->create(['reliability_score' => 80]);
    $newSource = PhoneSource::factory()->create(['reliability_score' => 82]);

    $result = (new ConflictDetector)->evaluate(5000, $existingSource, 5100, $newSource);

    expect($result)->not->toBeNull()
        ->and($result['action'])->toBe('flag');
});

it('auto-resolves in favour of a meaningfully more reliable new source', function () {
    $existingSource = PhoneSource::factory()->create(['type' => SourceTypeEnum::AI_ASSISTED, 'reliability_score' => 45]);
    $newSource = PhoneSource::factory()->create(['type' => SourceTypeEnum::MANUFACTURER, 'reliability_score' => 95]);

    $result = (new ConflictDetector)->evaluate(5000, $existingSource, 5100, $newSource);

    expect($result['action'])->toBe('auto_resolve');
});

it('does nothing when there is no existing value to conflict with', function () {
    $newSource = PhoneSource::factory()->create();

    $result = (new ConflictDetector)->evaluate(null, null, 5000, $newSource);

    expect($result)->toBeNull();
});
