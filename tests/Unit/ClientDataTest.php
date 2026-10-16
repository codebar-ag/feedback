<?php

declare(strict_types=1);

use App\Enums\PrecisionMode;
use App\Support\ClientData;
use App\Support\UserAgentParser;

const UA_CHROME_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0';
const UA_SAFARI_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15';
const UA_FIREFOX_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0';
const UA_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

it('parses the client payload', function (): void {
    $client = ClientData::fromArray([
        'userAgent' => ' '.UA_FIREFOX_WIN.' ',
        'viewport' => ['w' => 1541, 'h' => 947.4],
        'screen' => ['w' => '1800', 'h' => 1169],
        'page' => ['w' => 1541, 'h' => 6120],
        'scroll' => ['x' => -5, 'y' => 320],
        'dpr' => 2.123,
        'colorDepth' => 30,
        'timezone' => 'Europe/Zurich',
        'language' => 'de-CH',
        'maxTouchPoints' => 5,
        'online' => true,
    ]);

    expect($client)
        ->userAgent->toBe(UA_FIREFOX_WIN)
        ->viewportW->toBe(1541)
        ->viewportH->toBe(947)
        ->screenW->toBe(1800)
        ->pageH->toBe(6120)
        ->scrollX->toBe(0)
        ->scrollY->toBe(320)
        ->dpr->toBe(2.12)
        ->colorDepth->toBe(30)
        ->timezone->toBe('Europe/Zurich')
        ->language->toBe('de-CH')
        ->maxTouchPoints->toBe(5)
        ->online->toBeTrue()
        ->uaData->toBeNull()
        ->and($client->hasHighEntropyHints())->toBeFalse();
});

it('ignores malformed values', function (): void {
    $client = ClientData::fromArray(['viewport' => 'x', 'screen' => ['w' => 'abc'], 'dpr' => 'two', 'colorDepth' => 9999, 'online' => 'yes', 'timezone' => '', 'uaData' => ['platform' => 'macOS', 3 => 'x']]);

    expect($client)
        ->viewportW->toBeNull()
        ->screenW->toBeNull()
        ->dpr->toBeNull()
        ->colorDepth->toBe(255)
        ->online->toBeNull()
        ->timezone->toBeNull()
        ->uaData->toBe(['platform' => 'macOS']);
});

it('detects high entropy client hints', function (): void {
    expect(ClientData::isHighEntropy(['platformVersion' => '26.6.2', 'fullVersionList' => [['brand' => 'Microsoft Edge', 'version' => '154.0.3405.86']]]))->toBeTrue()
        ->and(ClientData::isHighEntropy(['platformVersion' => '26.6.2', 'fullVersionList' => []]))->toBeFalse()
        ->and(ClientData::isHighEntropy(['fullVersionList' => [['brand' => 'x', 'version' => '1']]]))->toBeFalse()
        ->and(ClientData::isHighEntropy(null))->toBeFalse();
});

it('uses client hints for exact versions', function (): void {
    $agent = (new UserAgentParser)->parse(UA_CHROME_MAC, [
        'platform' => 'macOS',
        'platformVersion' => '26.6.2',
        'mobile' => false,
        'fullVersionList' => [
            ['brand' => 'Not=A?Brand', 'version' => '24.0.0.0'],
            ['brand' => 'Chromium', 'version' => '154.0.3405.86'],
            ['brand' => 'Microsoft Edge', 'version' => '154.0.3405.86'],
        ],
    ]);

    expect($agent)
        ->osName->toBe('macOS')
        ->osVersion->toBe('26.6.2')
        ->browserName->toBe('Microsoft Edge')
        ->browserVersion->toBe('154.0.3405.86')
        ->deviceType->toBe('desktop')
        ->precision->toBe(PrecisionMode::Exact);
});

it('falls back to the user agent with approximate precision', function (string $ua, string $os, string $browser, string $device): void {
    $agent = (new UserAgentParser)->parse($ua, null);

    expect($agent)
        ->osName->toBe($os)
        ->browserName->toBe($browser)
        ->deviceType->toBe($device)
        ->precision->toBe(PrecisionMode::Approximate);
})->with([
    'safari' => [UA_SAFARI_MAC, 'macOS', 'Safari', 'desktop'],
    'firefox' => [UA_FIREFOX_WIN, 'Windows', 'Firefox', 'desktop'],
    'iphone' => [UA_IPHONE, 'iOS', 'Mobile Safari', 'smartphone'],
]);

it('handles an unknown user agent', function (): void {
    $agent = (new UserAgentParser)->parse(null, ['brands' => [['brand' => 'X', 'version' => '1']], 'mobile' => true]);

    expect($agent->osName)->toBeNull()
        ->and($agent->precision)->toBe(PrecisionMode::Approximate);
});
