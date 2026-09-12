<?php

use App\Enums\ImportRunStatusEnum;
use App\Enums\SourceTypeEnum;
use App\Models\Phone;
use App\Models\PhoneImportRun;
use App\Models\PhoneSource;
use App\Services\PhoneImport\Contracts\PhoneSourceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

class AlwaysFailsPhoneSource implements PhoneSourceProvider
{
    public function __construct(protected string $key, protected array $config) {}

    public function key(): string
    {
        return $this->key;
    }

    public function type(): SourceTypeEnum
    {
        return SourceTypeEnum::BD_RETAILER;
    }

    public function fetchBatch(?array $cursor, int $limit): array
    {
        throw new RuntimeException('Simulated retailer outage');
    }
}

class AlwaysSucceedsPhoneSource implements PhoneSourceProvider
{
    public function __construct(protected string $key, protected array $config) {}

    public function key(): string
    {
        return $this->key;
    }

    public function type(): SourceTypeEnum
    {
        return SourceTypeEnum::BD_RETAILER;
    }

    public function fetchBatch(?array $cursor, int $limit): array
    {
        return ['items' => [makePhoneRaw('Healthy Retailer Phone')], 'cursor' => null, 'done' => true];
    }
}

it('isolates one failed retailer source so every other source still completes and is logged separately', function () {
    config(['phone_sources.sources' => [
        'broken_retailer' => [
            'class' => AlwaysFailsPhoneSource::class, 'name' => 'Broken Retailer', 'type' => 'bd_retailer',
            'enabled' => true, 'reliability_score' => 70, 'requires_review' => false, 'config' => [],
        ],
        'healthy_retailer' => [
            'class' => AlwaysSucceedsPhoneSource::class, 'name' => 'Healthy Retailer', 'type' => 'bd_retailer',
            'enabled' => true, 'reliability_score' => 70, 'requires_review' => false, 'config' => [],
        ],
    ]]);

    $this->artisan('phones:import')->assertExitCode(1); // overall failure reported, but per source below...

    $broken = PhoneSource::query()->where('key', 'broken_retailer')->firstOrFail();
    $healthy = PhoneSource::query()->where('key', 'healthy_retailer')->firstOrFail();

    expect(PhoneImportRun::query()->where('source_id', $broken->id)->first()->status)->toBe(ImportRunStatusEnum::PARTIAL)
        ->and(PhoneImportRun::query()->where('source_id', $healthy->id)->first()->status)->toBe(ImportRunStatusEnum::COMPLETED);

    // The broken source's outage never stopped the healthy one's phone from being imported.
    expect(Phone::query()->where('slug', 'samsung-healthy-retailer-phone')->exists())->toBeTrue();
});

it('does not reset an admin-adjusted reliability_score back to the config default on a later import run', function () {
    config(['phone_sources.sources' => [
        'healthy_retailer' => [
            'class' => AlwaysSucceedsPhoneSource::class, 'name' => 'Healthy Retailer', 'type' => 'bd_retailer',
            'enabled' => true, 'reliability_score' => 70, 'requires_review' => false, 'config' => [],
        ],
    ]]);

    $this->artisan('phones:import')->assertExitCode(0);
    $source = PhoneSource::query()->where('key', 'healthy_retailer')->firstOrFail();
    expect($source->reliability_score)->toBe(70);

    // Simulates an admin marking the source unreliable after a real-world
    // confirmation it's bad (DataReviewController::markSourceUnreliable) -
    // this is documented (PhoneSourceRegistry's class docblock) as
    // admin-editable state that config only ever seeds, never overwrites.
    $source->update(['reliability_score' => 15]);

    $this->artisan('phones:import')->assertExitCode(0);

    expect($source->fresh()->reliability_score)->toBe(15);
});

it('treats a run stuck at RUNNING with no progress in over 10 minutes as abandoned and resumes it', function () {
    config(['phone_sources.sources' => [
        'healthy_retailer' => [
            'class' => AlwaysSucceedsPhoneSource::class, 'name' => 'Healthy Retailer', 'type' => 'bd_retailer',
            'enabled' => true, 'reliability_score' => 70, 'requires_review' => false, 'config' => [],
        ],
    ]]);

    $this->artisan('phones:import')->assertExitCode(0);

    $run = PhoneImportRun::query()->firstOrFail();
    expect($run->status)->toBe(ImportRunStatusEnum::COMPLETED);

    // Simulate a process that died mid-run: stuck at RUNNING, no checkpoint
    // update for well over the 10-minute staleness window. Eloquent's
    // auto-touch would otherwise overwrite an explicit 'updated_at' passed
    // to update() back to now() - disable it to actually backdate the row.
    $run->status = ImportRunStatusEnum::RUNNING;
    $run->updated_at = now()->subMinutes(15);
    $run->timestamps = false;
    $run->save();

    $this->artisan('phones:import')
        ->expectsOutputToContain('stuck at RUNNING')
        ->assertExitCode(0);

    // Recovered and completed the SAME run row - not left stuck, and no
    // duplicate run created for the source.
    expect(PhoneImportRun::query()->count())->toBe(1)
        ->and($run->fresh()->status)->toBe(ImportRunStatusEnum::COMPLETED);
});

it('does not touch a RUNNING run that is still recently active - only stale ones are treated as abandoned', function () {
    config(['phone_sources.sources' => [
        'healthy_retailer' => [
            'class' => AlwaysSucceedsPhoneSource::class, 'name' => 'Healthy Retailer', 'type' => 'bd_retailer',
            'enabled' => true, 'reliability_score' => 70, 'requires_review' => false, 'config' => [],
        ],
    ]]);

    $this->artisan('phones:import')->assertExitCode(0);
    $source = PhoneSource::query()->where('key', 'healthy_retailer')->firstOrFail();

    // A second, genuinely-in-progress run (e.g. a concurrent invocation) -
    // recently touched, must not be mistaken for an abandoned one.
    $activeRun = PhoneImportRun::create([
        'source_id' => $source->id,
        'type' => 'manual',
        'status' => ImportRunStatusEnum::RUNNING,
        'started_at' => now(),
    ]);

    $this->artisan('phones:import')
        ->doesntExpectOutputToContain('stuck at RUNNING')
        ->assertExitCode(0);

    expect($activeRun->fresh()->status)->toBe(ImportRunStatusEnum::RUNNING)
        ->and(PhoneImportRun::query()->count())->toBe(3); // original completed + still-running + new completed
});
