<?php

declare(strict_types=1);

use App\Support\PublicKeyGenerator;

it('generates keys in the fbk_live_ format', function (): void {
    $key = PublicKeyGenerator::generate();

    expect($key)->toStartWith('fbk_live_')
        ->and(strlen($key))->toBe(31)
        ->and(PublicKeyGenerator::isValid($key))->toBeTrue();
});

it('generates unique keys with enough entropy', function (): void {
    $keys = array_map(fn (): string => PublicKeyGenerator::generate(), range(1, 2000));
    $chars = count_chars(implode('', array_map(fn (string $k): string => substr($k, 9), $keys)), 3);

    expect(array_unique($keys))->toHaveCount(2000)
        // 22 characters out of 62 symbols: ~131 bits, every symbol shows up.
        ->and(strlen($chars))->toBe(62)
        ->and(22 * log(62, 2))->toBeGreaterThan(128);
});

it('rejects malformed keys', function (string $key): void {
    expect(PublicKeyGenerator::isValid($key))->toBeFalse();
})->with(['fbk_live_short', 'fbk_test_AAAAAAAAAAAAAAAAAAAAAA', 'fbk_live_AAAAAAAAAAAAAAAAAAAAA!', '']);
