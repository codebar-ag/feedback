<?php

declare(strict_types=1);

use App\Enums\IpMode;
use App\Support\IpAnonymizer;

it('stores IPs according to the mode', function (?string $ip, IpMode $mode, ?string $expected): void {
    expect(IpAnonymizer::apply($ip, $mode))->toBe($expected);
})->with([
    ['203.0.113.7', IpMode::Full, '203.0.113.7'],
    ['203.0.113.7', IpMode::Anonymized, '203.0.113.0'],
    ['2001:db8:abcd:1234:5678:9abc:def0:1234', IpMode::Anonymized, '2001:db8:abcd::'],
    ['2001:db8::1', IpMode::Full, '2001:db8::1'],
    [null, IpMode::Full, null],
    ['garbage', IpMode::Anonymized, null],
]);
