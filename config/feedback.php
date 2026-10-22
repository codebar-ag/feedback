<?php

declare(strict_types=1);

/*
 * Limit from the environment: unset or empty uses the default, 0 (or any
 * value below 1) switches the limit off.
 */
$limit = static function (string $key, int $default): ?int {
    $value = env($key);

    if ($value === null || $value === '') {
        return $default;
    }

    $int = is_numeric($value) ? (int) $value : $default;

    return $int > 0 ? $int : null;
};

return [

    /*
    |--------------------------------------------------------------------------
    | Public endpoint
    |--------------------------------------------------------------------------
    |
    | Base URL of this API as seen by the widget, e.g. https://feedback.codebar.ch.
    | Used for the init snippet printed by the feedback:project:* commands.
    |
    */

    'endpoint' => env('FEEDBACK_ENDPOINT', env('APP_URL', 'http://localhost')),

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
    | Rate limits and minimum fill time
    |--------------------------------------------------------------------------
    |
    | Safe defaults for public installations. Set a value in .env to change
    | it, 0 switches the respective limit off. The daily limit per project is
    | stored on the project (--limit option, no default).
    |
    */

    'limits' => [
        'per_ip_per_minute' => $limit('FEEDBACK_LIMIT_PER_IP_PER_MINUTE', 10),
        'per_project_per_minute' => $limit('FEEDBACK_LIMIT_PER_PROJECT_PER_MINUTE', 60),
        'min_elapsed_ms' => $limit('FEEDBACK_MIN_ELAPSED_MS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget for script tags
    |--------------------------------------------------------------------------
    |
    | The deploy build (npm run build in the root) copies the script tag build
    | here; the API serves it at /widget/v1/feedback.js.
    |
    */

    'widget_path' => resource_path('widget/v1'),

    /*
    |--------------------------------------------------------------------------
    | Share link after sending
    |--------------------------------------------------------------------------
    |
    | Minutes the signed link returned to the widget stays valid. The thank
    | you screen shows it with a copy button. 0 switches it off. Links for the
    | team are created with feedback:link.
    |
    */

    'share_link_minutes' => ($minutes = $limit('FEEDBACK_SHARE_LINK_MINUTES', 7 * 24 * 60)) === null ? null : min($minutes, 30 * 24 * 60),

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
