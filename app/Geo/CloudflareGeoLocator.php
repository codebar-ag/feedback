<?php

declare(strict_types=1);

namespace App\Geo;

use Illuminate\Http\Request;

/**
 * Reads the Cloudflare visitor location headers. Only trustworthy when the
 * API is reachable exclusively through Cloudflare.
 */
final readonly class CloudflareGeoLocator implements GeoLocator
{
    public function locate(Request $request, string $ip): ?GeoLocation
    {
        $location = new GeoLocation(
            country: self::header($request, 'CF-IPCountry', 2),
            city: self::header($request, 'CF-IPCity', 120),
            postcode: self::header($request, 'CF-Postal-Code', 20),
        );

        return $location->isEmpty() ? null : $location;
    }

    private static function header(Request $request, string $name, int $max): ?string
    {
        $value = $request->headers->get($name);

        if ($value === null || $value === '' || in_array(strtoupper($value), ['XX', 'T1'], true)) {
            return null;
        }

        return mb_substr(trim($value), 0, $max);
    }
}
