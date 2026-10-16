<?php

declare(strict_types=1);

namespace App\Geo;

use Closure;
use GeoIp2\ProviderInterface;
use Illuminate\Http\Request;
use Throwable;

/** Looks up the IP in a local GeoLite2 City database. No external calls. */
final readonly class MaxMindGeoLocator implements GeoLocator
{
    /** @param Closure(): ?ProviderInterface $reader */
    public function __construct(private Closure $reader) {}

    public function locate(Request $request, string $ip): ?GeoLocation
    {
        try {
            $reader = ($this->reader)();

            if ($reader === null) {
                return null;
            }

            $record = $reader->city($ip);
            $location = new GeoLocation(
                country: $record->country->isoCode,
                city: $record->city->name,
                postcode: $record->postal->code,
            );

            return $location->isEmpty() ? null : $location;
        } catch (Throwable) {
            // Unknown address, private range or unreadable database: no location.
            return null;
        }
    }
}
