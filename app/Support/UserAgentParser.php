<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PrecisionMode;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\AbstractParser;

/**
 * Resolves OS, browser and device from the raw user agent plus optional
 * Client Hints with matomo/device-detector. Runs on the server so that
 * detection fixes do not need a new widget release.
 */
final readonly class UserAgentParser
{
    private const array OS_NAMES = ['Mac' => 'macOS', 'GNU/Linux' => 'Linux'];

    /** @param array<string, mixed>|null $uaData */
    public function parse(?string $userAgent, ?array $uaData): ParsedAgent
    {
        $hints = $uaData !== null ? ClientHints::factory(self::hintHeaders($uaData)) : null;
        // Keep full versions (e.g. macOS 26.6.2), detection is truncated to minor by default.
        AbstractParser::setVersionTruncation(AbstractParser::VERSION_TRUNCATION_NONE);

        $detector = new DeviceDetector($userAgent ?? '', $hints);
        $detector->discardBotInformation();
        $detector->parse();

        $osName = self::value($detector->getOs('name'));

        return new ParsedAgent(
            osName: $osName === null ? null : (self::OS_NAMES[$osName] ?? $osName),
            osVersion: self::value($detector->getOs('version')),
            browserName: self::value($detector->getClient('name')),
            browserVersion: self::value($detector->getClient('version')),
            deviceType: self::value($detector->getDeviceName()),
            precision: ClientData::isHighEntropy($uaData) ? PrecisionMode::Exact : PrecisionMode::Approximate,
        );
    }

    /**
     * Maps the JS NavigatorUAData shape to the header names ClientHints understands.
     *
     * @param  array<string, mixed>  $uaData
     * @return array<string, mixed>
     */
    private static function hintHeaders(array $uaData): array
    {
        $headers = [];

        foreach (['platform' => 'sec-ch-ua-platform', 'platformVersion' => 'sec-ch-ua-platform-version', 'model' => 'sec-ch-ua-model', 'architecture' => 'sec-ch-ua-arch', 'bitness' => 'sec-ch-ua-bitness'] as $key => $header) {
            if (is_string($uaData[$key] ?? null)) {
                $headers[$header] = $uaData[$key];
            }
        }

        if (is_bool($uaData['mobile'] ?? null)) {
            $headers['sec-ch-ua-mobile'] = $uaData['mobile'] ? '?1' : '?0';
        }

        $list = $uaData['fullVersionList'] ?? ($uaData['brands'] ?? null);

        if (is_array($list)) {
            $parts = [];

            foreach ($list as $entry) {
                if (is_array($entry) && is_string($entry['brand'] ?? null) && is_string($entry['version'] ?? null)) {
                    $parts[] = sprintf('"%s";v="%s"', str_replace('"', '', $entry['brand']), str_replace('"', '', $entry['version']));
                }
            }

            if ($parts !== []) {
                $headers['sec-ch-ua-full-version-list'] = implode(', ', $parts);
            }
        }

        return $headers;
    }

    private static function value(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value === 'UNK' || $value === DeviceDetector::UNKNOWN) {
            return null;
        }

        return $value;
    }
}
