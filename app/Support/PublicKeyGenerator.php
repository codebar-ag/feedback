<?php

declare(strict_types=1);

namespace App\Support;

/** Generates public project keys in the form fbk_live_<22 base62 chars> (~131 bit). */
final readonly class PublicKeyGenerator
{
    public const string PREFIX = 'fbk_live_';

    public const int LENGTH = 22;

    private const string ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function generate(): string
    {
        $key = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $key .= self::ALPHABET[random_int(0, $max)];
        }

        return self::PREFIX.$key;
    }

    public static function isValid(string $key): bool
    {
        return preg_match('/^'.self::PREFIX.'[0-9A-Za-z]{'.self::LENGTH.'}$/', $key) === 1;
    }
}
