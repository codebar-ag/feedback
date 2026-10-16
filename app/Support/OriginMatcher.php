<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Matches a browser Origin header against the allowed origin patterns of a
 * project.
 *
 *   https://app.example.ch   exact origin (scheme, host, port)
 *   https://*.example.ch     every subdomain, not example.ch itself
 *   http://localhost:*       any port
 *   *                        any origin
 */
final readonly class OriginMatcher
{
    private const string PATTERN = '#^(https?)://((?:\*\.)?[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)(?::(\d{1,5}|\*))?$#i';

    /** @param list<string> $patterns */
    public function __construct(private array $patterns) {}

    public function allows(?string $origin): bool
    {
        $parsed = self::parse($origin, allowWildcards: false);

        if ($parsed === null) {
            return false;
        }

        foreach ($this->patterns as $pattern) {
            if ($pattern === '*' || self::matches($parsed, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function isValidPattern(string $pattern): bool
    {
        return $pattern === '*' || self::parse($pattern, allowWildcards: true) !== null;
    }

    /** Normalizes a pattern (lower case, no trailing slash). */
    public static function normalize(string $pattern): string
    {
        $pattern = rtrim(trim($pattern), '/');

        return $pattern === '*' ? '*' : strtolower($pattern);
    }

    /** @param array{scheme: string, host: string, port: string} $origin */
    private static function matches(array $origin, string $pattern): bool
    {
        $parsed = self::parse($pattern, allowWildcards: true);

        if ($parsed === null || $parsed['scheme'] !== $origin['scheme']) {
            return false;
        }

        $portMatches = $parsed['port'] === '*' || $parsed['port'] === $origin['port'];

        if (str_starts_with($parsed['host'], '*.')) {
            $suffix = substr($parsed['host'], 1);
            $hostMatches = str_ends_with($origin['host'], $suffix)
                && strlen($origin['host']) > strlen($suffix);
        } else {
            $hostMatches = $parsed['host'] === $origin['host'];
        }

        return $portMatches && $hostMatches;
    }

    /** @return array{scheme: string, host: string, port: string}|null */
    private static function parse(?string $value, bool $allowWildcards): ?array
    {
        if ($value === null || $value === '' || preg_match(self::PATTERN, $value, $m) !== 1) {
            return null;
        }

        $host = strtolower($m[2]);
        $port = $m[3] ?? '';

        if (! $allowWildcards && ($port === '*' || str_starts_with($host, '*.'))) {
            return null;
        }

        if (str_contains($host, '..')) {
            return null;
        }

        $scheme = strtolower($m[1]);

        if ($port === '') {
            $port = $scheme === 'https' ? '443' : '80';
        } elseif ($port !== '*') {
            $port = (string) (int) $port;
        }

        return ['scheme' => $scheme, 'host' => $host, 'port' => $port];
    }
}
