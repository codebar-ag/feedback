<?php

declare(strict_types=1);

namespace App\Geo;

use Illuminate\Http\Request;

interface GeoLocator
{
    public function locate(Request $request, string $ip): ?GeoLocation;
}
