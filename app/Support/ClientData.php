<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Browser and system data sent by the widget in the "client" JSON field
 *. Every value is optional and normalized defensively.
 */
final readonly class ClientData
{
    /**
     * @param  array<string, mixed>|null  $uaData  User-Agent Client Hints (getHighEntropyValues)
     */
    public function __construct(
        public ?string $userAgent,
        public ?array $uaData,
        public ?int $viewportW,
        public ?int $viewportH,
        public ?int $screenW,
        public ?int $screenH,
        public ?int $pageW,
        public ?int $pageH,
        public ?int $scrollX,
        public ?int $scrollY,
        public ?float $dpr,
        public ?int $colorDepth,
        public ?string $timezone,
        public ?string $language,
        public ?int $maxTouchPoints,
        public ?bool $online,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $uaData = $data['uaData'] ?? null;

        return new self(
            userAgent: self::string($data['userAgent'] ?? null, 1024),
            uaData: is_array($uaData) ? self::stringKeys($uaData) : null,
            viewportW: self::uint(self::path($data, 'viewport', 'w')),
            viewportH: self::uint(self::path($data, 'viewport', 'h')),
            screenW: self::uint(self::path($data, 'screen', 'w')),
            screenH: self::uint(self::path($data, 'screen', 'h')),
            pageW: self::uint(self::path($data, 'page', 'w')),
            pageH: self::uint(self::path($data, 'page', 'h')),
            scrollX: self::uint(self::path($data, 'scroll', 'x')),
            scrollY: self::uint(self::path($data, 'scroll', 'y')),
            dpr: self::dpr($data['dpr'] ?? null),
            colorDepth: self::uint($data['colorDepth'] ?? null, 255),
            timezone: self::string($data['timezone'] ?? null, 64),
            language: self::string($data['language'] ?? null, 35),
            maxTouchPoints: self::uint($data['maxTouchPoints'] ?? null, 255),
            online: is_bool($data['online'] ?? null) ? $data['online'] : null,
        );
    }

    /** True when Client Hints with platform version and full version list were sent. */
    public function hasHighEntropyHints(): bool
    {
        return self::isHighEntropy($this->uaData);
    }

    /** @param array<string, mixed>|null $uaData */
    public static function isHighEntropy(?array $uaData): bool
    {
        $list = $uaData['fullVersionList'] ?? null;
        $platformVersion = $uaData['platformVersion'] ?? null;

        return is_array($list) && $list !== [] && is_string($platformVersion) && $platformVersion !== '';
    }

    /** @param array<mixed> $data */
    private static function path(array $data, string $key, string $sub): mixed
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? ($value[$sub] ?? null) : null;
    }

    private static function uint(mixed $value, int $max = 4_294_967_295): ?int
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        $int = (int) round((float) $value);

        return $int < 0 ? 0 : min($int, $max);
    }

    private static function dpr(mixed $value): ?float
    {
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        return max(0.0, min(99.99, round((float) $value, 2)));
    }

    private static function string(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    private static function stringKeys(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
