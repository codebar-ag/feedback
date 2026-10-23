<?php

declare(strict_types=1);

namespace App\Checks;

use Illuminate\Database\Migrations\Migrator;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Fails when a migration file has not run against this database: the
 * deploy command's `migrate --force` failed or was commented out, and the
 * deployed code expects a schema the database does not have.
 */
final class PendingMigrationsCheck extends Check
{
    public const int REPORTED_MIGRATIONS = 5;

    public function run(): Result
    {
        $result = Result::make();

        try {
            $pending = $this->pending();
        } catch (Throwable $exception) {
            return $result->shortSummary('unreadable')->failed("Cannot tell which migrations have run: {$exception->getMessage()}");
        }

        if ($pending === null) {
            return $result->shortSummary('no migrations table')->failed('The migrations table does not exist, so this database has never been migrated.');
        }

        $result->shortSummary($pending === [] ? 'up to date' : count($pending).' pending');

        if ($pending === []) {
            return $result->ok();
        }

        $reported = array_slice($pending, 0, self::REPORTED_MIGRATIONS);
        $hidden = count($pending) - count($reported);

        return $result->failed(
            count($pending).' migration(s) have not run: '.implode(', ', $reported)
                .($hidden > 0 ? ", and {$hidden} more" : '')
                .'. Run `php artisan migrate --force`.',
        );
    }

    /**
     * @return list<string>|null
     */
    private function pending(): ?array
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            return null;
        }

        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));

        return array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }
}
