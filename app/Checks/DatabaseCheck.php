<?php

declare(strict_types=1);

namespace App\Checks;

use Spatie\Health\Checks\Checks\DatabaseCheck as SpatieDatabaseCheck;
use Spatie\Health\Checks\Result;

/**
 * spatie's DatabaseCheck, reporting ok for apps without a database.
 */
final class DatabaseCheck extends SpatieDatabaseCheck
{
    public function run(): Result
    {
        if (! DatabaseAvailability::isAvailable()) {
            return Result::make()->shortSummary('no database')->ok();
        }

        return parent::run();
    }
}
