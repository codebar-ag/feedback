<?php

declare(strict_types=1);

use App\Support\OriginMatcher;

it('matches origins', function (array $patterns, ?string $origin, bool $expected): void {
    expect((new OriginMatcher($patterns))->allows($origin))->toBe($expected);
})->with([
    'exact' => [['https://app.beispiel.ch'], 'https://app.beispiel.ch', true],
    'exact, explicit default port' => [['https://app.beispiel.ch'], 'https://app.beispiel.ch:443', true],
    'case insensitive' => [['https://App.Beispiel.ch'], 'https://app.BEISPIEL.ch', true],
    'other host' => [['https://app.beispiel.ch'], 'https://www.beispiel.ch', false],
    'scheme differs' => [['https://app.beispiel.ch'], 'http://app.beispiel.ch', false],
    'port differs' => [['https://app.beispiel.ch'], 'https://app.beispiel.ch:8443', false],
    'explicit port' => [['https://app.beispiel.ch:8443'], 'https://app.beispiel.ch:8443', true],
    'wildcard subdomain' => [['https://*.beispiel.ch'], 'https://www.beispiel.ch', true],
    'wildcard deep subdomain' => [['https://*.beispiel.ch'], 'https://a.b.beispiel.ch', true],
    'wildcard excludes apex' => [['https://*.beispiel.ch'], 'https://beispiel.ch', false],
    'wildcard suffix attack' => [['https://*.beispiel.ch'], 'https://evilbeispiel.ch', false],
    'host suffix attack' => [['https://app.beispiel.ch'], 'https://app.beispiel.ch.evil.com', false],
    'wildcard suffix attack 2' => [['https://*.beispiel.ch'], 'https://app.beispiel.ch.evil.com', false],
    'any port' => [['http://localhost:*'], 'http://localhost:5173', true],
    'any port, no port' => [['http://localhost:*'], 'http://localhost', true],
    'any port, other host' => [['http://localhost:*'], 'http://127.0.0.1:5173', false],
    'star' => [['*'], 'https://irgendwo.example', true],
    'null origin' => [['*'], null, false],
    'empty origin' => [['https://app.beispiel.ch'], '', false],
    'literal null' => [['*'], 'null', false],
    'origin with path' => [['https://app.beispiel.ch'], 'https://app.beispiel.ch/x', false],
    'origin with wildcard' => [['https://*.beispiel.ch'], 'https://*.beispiel.ch', false],
    'origin with port wildcard' => [['http://localhost:*'], 'http://localhost:*', false],
    'origin with userinfo' => [['https://app.beispiel.ch'], 'https://user@app.beispiel.ch', false],
    'second pattern' => [['https://a.ch', 'https://b.ch'], 'https://b.ch', true],
    'no patterns' => [[], 'https://b.ch', false],
    'invalid pattern ignored' => [['ftp://b.ch'], 'https://b.ch', false],
]);

it('validates patterns', function (string $pattern, bool $valid): void {
    expect(OriginMatcher::isValidPattern($pattern))->toBe($valid);
})->with([
    ['https://www.beispiel.ch', true],
    ['https://*.beispiel.ch', true],
    ['http://localhost:*', true],
    ['http://localhost:8080', true],
    ['*', true],
    ['www.beispiel.ch', false],
    ['https://www.beispiel.ch/path', false],
    ['ftp://beispiel.ch', false],
    ['https://*', false],
    ['https://a..b.ch', false],
    ['https://-a.ch', false],
    ['', false],
]);

it('normalizes patterns', function (): void {
    expect(OriginMatcher::normalize(' HTTPS://WWW.Beispiel.ch/ '))->toBe('https://www.beispiel.ch')
        ->and(OriginMatcher::normalize('*'))->toBe('*');
});
