<?php

declare(strict_types=1);

use App\Support\IpMatcher;

it('checks IPs against single addresses and ranges', function (array $allowed, ?string $ip, bool $expected): void {
    expect((new IpMatcher($allowed))->allows($ip))->toBe($expected);
})->with([
    'empty list allows all' => [[], '198.51.100.1', true],
    'empty list allows null' => [[], null, true],
    'single v4' => [['203.0.113.7'], '203.0.113.7', true],
    'single v4 other' => [['203.0.113.7'], '203.0.113.8', false],
    'cidr v4 inside' => [['10.0.0.0/8'], '10.255.1.2', true],
    'cidr v4 lower bound' => [['10.0.0.0/8'], '10.0.0.0', true],
    'cidr v4 upper bound' => [['10.0.0.0/8'], '10.255.255.255', true],
    'cidr v4 outside' => [['10.0.0.0/8'], '11.0.0.0', false],
    'cidr /32' => [['192.0.2.1/32'], '192.0.2.1', true],
    'cidr /0' => [['0.0.0.0/0'], '8.8.8.8', true],
    'single v6' => [['2001:db8::1'], '2001:db8::1', true],
    'cidr v6 inside' => [['2001:db8::/32'], '2001:db8:abcd::1', true],
    'cidr v6 outside' => [['2001:db8::/32'], '2001:db9::1', false],
    'v4 against v6 range' => [['2001:db8::/32'], '10.0.0.1', false],
    'null ip' => [['10.0.0.0/8'], null, false],
    'garbage ip' => [['10.0.0.0/8'], 'not-an-ip', false],
    'multiple entries' => [['192.0.2.1', '10.0.0.0/8'], '10.1.1.1', true],
]);

it('validates entries', function (string $entry, bool $valid): void {
    expect(IpMatcher::isValid($entry))->toBe($valid);
})->with([
    ['203.0.113.7', true],
    ['10.0.0.0/8', true],
    ['10.0.0.0/32', true],
    ['10.0.0.0/33', false],
    ['2001:db8::/32', true],
    ['2001:db8::/128', true],
    ['2001:db8::/129', false],
    ['::1', true],
    ['256.0.0.1', false],
    ['10.0.0.0/', false],
    ['10.0.0.0/a', false],
    ['10.0.0.0/8/8', false],
    ['example.ch', false],
]);
