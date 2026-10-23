<?php

declare(strict_types=1);

namespace App\Checks;

/**
 * Health checks that need a database ask here first. Apps without a
 * database set `health.uses_database` to false in config/health.php; their
 * database checks then report ok ("no database") instead of failing, so
 * every app registers the same list of checks.
 */
final class DatabaseAvailability
{
    public static function isAvailable(): bool
    {
        return config('health.uses_database', true) !== false;
    }
}
