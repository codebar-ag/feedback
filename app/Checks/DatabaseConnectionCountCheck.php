<?php

declare(strict_types=1);

namespace App\Checks;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;
use Spatie\Health\Checks\Checks\DatabaseConnectionCountCheck as SpatieDatabaseConnectionCountCheck;
use Spatie\Health\Checks\Result;

/**
 * Informational only: reports the number of open connections of the app's
 * own database and never warns or fails. On PostgreSQL only connections to
 * the current database count, because Neon servers are shared between apps.
 * Apps without a database and SQLite report ok.
 */
class DatabaseConnectionCountCheck extends SpatieDatabaseConnectionCountCheck
{
    public function run(): Result
    {
        if (! DatabaseAvailability::isAvailable()) {
            return Result::make()->shortSummary('no database')->ok();
        }

        if (config("database.connections.{$this->connection()}.driver") === 'sqlite') {
            return Result::make()->shortSummary('sqlite')->ok();
        }

        $connectionCount = $this->getConnectionCount();

        return Result::make()
            ->ok()
            ->meta(['connection_count' => $connectionCount])
            ->shortSummary($connectionCount.' '.Str::plural('connection', $connectionCount));
    }

    protected function getConnectionCount(): int
    {
        $connection = app(ConnectionResolverInterface::class)->connection($this->connection());

        if ($connection->getDriverName() !== 'pgsql') {
            return parent::getConnectionCount();
        }

        $count = $connection->scalar('select count(*) from pg_stat_activity where datname = current_database()');

        return is_numeric($count) ? (int) $count : 0;
    }

    private function connection(): string
    {
        return $this->connectionName ?? $this->getDefaultConnectionName();
    }
}
