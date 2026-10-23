<?php

declare(strict_types=1);

namespace App\Checks;

use Spatie\Health\Checks\Checks\DatabaseConnectionCountCheck as SpatieDatabaseConnectionCountCheck;
use Spatie\Health\Checks\Result;

/**
 * spatie's DatabaseConnectionCountCheck with the agreed thresholds (warn
 * above 50, fail above 80), reporting ok for apps without a database and
 * for SQLite, which has no connection count.
 */
class DatabaseConnectionCountCheck extends SpatieDatabaseConnectionCountCheck
{
    protected ?int $warningThreshold = 50;

    protected int $errorThreshold = 80;

    public function run(): Result
    {
        if (! DatabaseAvailability::isAvailable()) {
            return Result::make()->shortSummary('no database')->ok();
        }

        $connection = $this->connectionName ?? $this->getDefaultConnectionName();

        if (config("database.connections.{$connection}.driver") === 'sqlite') {
            return Result::make()->shortSummary('sqlite')->ok();
        }

        return parent::run();
    }
}
