<?php

declare(strict_types=1);

namespace App\Geo;

use Illuminate\Http\Request;

final readonly class NullGeoLocator implements GeoLocator
{
    public function locate(Request $request, string $ip): ?GeoLocation
    {
        return null;
    }
}
