<?php

declare(strict_types=1);

use App\Enums\ColorPreset;
use App\Enums\GeoSource;
use App\Enums\Locale;

it('knows the four supported locales', function (): void {
    expect(Locale::values())->toBe(['de_CH', 'fr_CH', 'en_CH', 'it_CH'])
        ->and(Locale::tryFrom('de_DE'))->toBeNull();
});

it('maps to BCP-47', function (Locale $locale, string $tag): void {
    expect($locale->bcp47())->toBe($tag);
})->with([
    [Locale::DeCh, 'de-CH'],
    [Locale::FrCh, 'fr-CH'],
    [Locale::EnCh, 'en-CH'],
    [Locale::ItCh, 'it-CH'],
]);

it('maps color presets', function (): void {
    expect(ColorPreset::Black->hex())->toBe('#000000')->and(ColorPreset::White->hex())->toBe('#FFFFFF');
});

it('reads the geo source from config', function (mixed $value, GeoSource $expected): void {
    expect(GeoSource::fromConfig($value))->toBe($expected);
})->with([
    ['none', GeoSource::None],
    ['off', GeoSource::None],
    [null, GeoSource::None],
    ['cloudflare', GeoSource::Cloudflare],
    [' MaxMind ', GeoSource::MaxMind],
]);
