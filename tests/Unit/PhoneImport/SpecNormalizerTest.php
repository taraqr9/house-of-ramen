<?php

use App\Services\PhoneImport\Normalization\SpecNormalizer;

it('normalizes ram from varied raw strings', function () {
    expect(SpecNormalizer::ramGb('8GB'))->toBe(8)
        ->and(SpecNormalizer::ramGb('8 GB'))->toBe(8)
        ->and(SpecNormalizer::ramGb('8 GB LPDDR5'))->toBe(8)
        ->and(SpecNormalizer::ramGb(8))->toBe(8)
        ->and(SpecNormalizer::ramGb(null))->toBeNull();
});

it('normalizes storage including terabyte values', function () {
    expect(SpecNormalizer::storageGb('256GB'))->toBe(256)
        ->and(SpecNormalizer::storageGb('1 TB'))->toBe(1024)
        ->and(SpecNormalizer::storageGb('1TB UFS 3.1'))->toBe(1024);
});

it('normalizes battery capacity from comma-formatted strings', function () {
    expect(SpecNormalizer::batteryMah('5000 mAh'))->toBe(5000)
        ->and(SpecNormalizer::batteryMah('5,000mAh'))->toBe(5000)
        ->and(SpecNormalizer::batteryMah(5000))->toBe(5000);
});

it('normalizes charging wattage', function () {
    expect(SpecNormalizer::wattage('67W'))->toBe(67)
        ->and(SpecNormalizer::wattage('120 W fast charging'))->toBe(120);
});

it('normalizes display size to inches', function () {
    expect(SpecNormalizer::displaySizeInches('6.7'))->toBe(6.7)
        ->and(SpecNormalizer::displaySizeInches('6.7 inch'))->toBe(6.7)
        ->and(SpecNormalizer::displaySizeInches(6.78))->toBe(6.8);
});

it('normalizes grouped-comma Bangladesh Taka amounts', function () {
    expect(SpecNormalizer::amount('1,79,999'))->toBe(179999.0)
        ->and(SpecNormalizer::amount('৳29,999'))->toBe(29999.0)
        ->and(SpecNormalizer::amount('Tk 29999'))->toBe(29999.0)
        ->and(SpecNormalizer::amount(29999))->toBe(29999.0);
});

it('parses dimension strings into height width thickness', function () {
    [$h, $w, $t] = SpecNormalizer::dimensionsMm('162.3 x 79.0 x 8.6');

    expect($h)->toBe(162.3)->and($w)->toBe(79.0)->and($t)->toBe(8.6);
});

it('returns nulls for an empty dimension string', function () {
    expect(SpecNormalizer::dimensionsMm(null))->toBe([null, null, null]);
});

it('normalizes loose boolean representations', function () {
    expect(SpecNormalizer::boolean(true))->toBeTrue()
        ->and(SpecNormalizer::boolean('true'))->toBeTrue()
        ->and(SpecNormalizer::boolean('yes'))->toBeTrue()
        ->and(SpecNormalizer::boolean('no'))->toBeFalse()
        ->and(SpecNormalizer::boolean(null))->toBeFalse();
});

it('builds a url-safe slug from multiple parts', function () {
    expect(SpecNormalizer::slug('Samsung', 'Galaxy S24 Ultra'))->toBe('samsung-galaxy-s24-ultra');
});

it('builds a phone slug from brand + model without duplicating the brand when the model already includes it', function () {
    // Real bug: several sources give a "model" that already contains the
    // brand ("Redmi" / "Redmi Note 14 Pro"), and naively concatenating
    // brand + model produced "redmi-redmi-note-14-pro" for 157 of 220
    // catalogue phones.
    expect(SpecNormalizer::phoneSlug('Redmi', 'Redmi Note 14 Pro'))->toBe('redmi-note-14-pro')
        ->and(SpecNormalizer::phoneSlug('POCO', 'POCO X6 Pro'))->toBe('poco-x6-pro')
        ->and(SpecNormalizer::phoneSlug('Xiaomi', 'Xiaomi 14 Ultra'))->toBe('xiaomi-14-ultra');
});

it('still prefixes the brand onto a phone slug when the model name does not already include it', function () {
    expect(SpecNormalizer::phoneSlug('Samsung', 'Galaxy S24 Ultra'))->toBe('samsung-galaxy-s24-ultra');
});
