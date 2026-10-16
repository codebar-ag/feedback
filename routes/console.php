<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Monthly refresh of the local GeoLite2 database, only when it is the location source.
Schedule::command('geoip:update')
    ->monthly()
    ->when(fn (): bool => config('feedback.geo') === 'maxmind');
