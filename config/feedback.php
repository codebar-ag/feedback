<?php

declare(strict_types=1);

$nullableInt = static function (string $key): ?int {
    $value = env($key);

    return $value === null || $value === '' ? null : (int) $value;
};

return [

    /*
    |--------------------------------------------------------------------------
    | Public endpoint
    |--------------------------------------------------------------------------
    |
    | Base URL of this API as seen by the widget. Used for the init snippet
    | printed by the feedback:project:* commands. Placeholder until the API
    | domain is decided (see README, "Zu entscheiden").
    |
    */

    'endpoint' => env('FEEDBACK_ENDPOINT', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | IP storage mode
    |--------------------------------------------------------------------------
    |
    | "full" stores the client IP as received, "anonymized" zeroes the last
    | IPv4 octet and the last 80 bits of an IPv6 address before storing.
    |
    */

    'ip_mode' => env('FEEDBACK_IP_MODE', 'full'),

    /*
    |--------------------------------------------------------------------------
    | Location source
    |--------------------------------------------------------------------------
    |
    | "none" (or "off"), "cloudflare" (CF-IPCountry / CF-IPCity / CF-Postal-Code
    | headers) or "maxmind" (local GeoLite2 database, see geoip:update).
    |
    */

    'geo' => env('FEEDBACK_GEO', 'none'),

    'maxmind' => [
        'database_path' => env('FEEDBACK_GEOIP_PATH', storage_path('app/geoip/GeoLite2-City.mmdb')),
        'account_id' => env('MAXMIND_ACCOUNT_ID'),
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        'edition' => env('MAXMIND_EDITION', 'GeoLite2-City'),
        'download_url' => env('MAXMIND_DOWNLOAD_URL', 'https://download.maxmind.com/geoip/databases/{edition}/download?suffix=tar.gz'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default project color
    |--------------------------------------------------------------------------
    |
    | Used when a project has no color set. "black", "white" or a hex value.
    |
    */

    'default_color' => env('FEEDBACK_DEFAULT_COLOR', 'black'),

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Deliberately without defaults: null disables the respective limit. The
    | daily limit per project is stored on the project (--limit option).
    |
    */

    'limits' => [
        'per_ip_per_minute' => $nullableInt('FEEDBACK_LIMIT_PER_IP_PER_MINUTE'),
        'per_project_per_minute' => $nullableInt('FEEDBACK_LIMIT_PER_PROJECT_PER_MINUTE'),
        'min_elapsed_ms' => $nullableInt('FEEDBACK_MIN_ELAPSED_MS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Screenshot limits
    |--------------------------------------------------------------------------
    */

    'max_screenshot_bytes' => 6 * 1024 * 1024,

    'max_screenshot_pixels' => 25_000_000,

    /*
    |--------------------------------------------------------------------------
    | Public config response
    |--------------------------------------------------------------------------
    |
    | Cache lifetime (seconds) of GET /v1/projects/{key}/config. Matches the
    | widget's own session cache of ten minutes.
    |
    */

    'config_max_age' => 600,

];
