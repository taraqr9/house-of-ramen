<?php

use App\Enums\PhoneRegionEnum;

it('resolves an explicit "Global" region to Global', function () {
    expect(PhoneRegionEnum::resolve('Global'))->toBe(PhoneRegionEnum::GLOBAL);
});

it('resolves "China" and "Chinese" to Chinese, case-insensitively', function () {
    expect(PhoneRegionEnum::resolve('China'))->toBe(PhoneRegionEnum::CHINESE)
        ->and(PhoneRegionEnum::resolve('china'))->toBe(PhoneRegionEnum::CHINESE)
        ->and(PhoneRegionEnum::resolve('CHINA'))->toBe(PhoneRegionEnum::CHINESE)
        ->and(PhoneRegionEnum::resolve('Chinese'))->toBe(PhoneRegionEnum::CHINESE)
        ->and(PhoneRegionEnum::resolve('  China  '))->toBe(PhoneRegionEnum::CHINESE);
});

it('treats a legacy "Bangladesh" region value as Global, never Chinese', function () {
    // Bangladesh is a market label, not a distinct hardware/firmware
    // version - see the variant-architecture audit. It must never be
    // silently reclassified as the Chinese-market version.
    expect(PhoneRegionEnum::resolve('Bangladesh'))->toBe(PhoneRegionEnum::GLOBAL);
});

it('treats a null or empty region as Global rather than throwing or guessing Chinese', function () {
    expect(PhoneRegionEnum::resolve(null))->toBe(PhoneRegionEnum::GLOBAL)
        ->and(PhoneRegionEnum::resolve(''))->toBe(PhoneRegionEnum::GLOBAL)
        ->and(PhoneRegionEnum::resolve('   '))->toBe(PhoneRegionEnum::GLOBAL);
});

it('treats any other unrecognized free-text region as Global, never assuming Chinese without evidence', function () {
    expect(PhoneRegionEnum::resolve('Middle East'))->toBe(PhoneRegionEnum::GLOBAL)
        ->and(PhoneRegionEnum::resolve('EU'))->toBe(PhoneRegionEnum::GLOBAL)
        ->and(PhoneRegionEnum::resolve('Some Unclassified Value'))->toBe(PhoneRegionEnum::GLOBAL);
});

it('labels each case for display', function () {
    expect(PhoneRegionEnum::GLOBAL->label())->toBe('Global')
        ->and(PhoneRegionEnum::CHINESE->label())->toBe('Chinese');
});
