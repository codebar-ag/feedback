<?php

declare(strict_types=1);

use App\Checks\PendingMigrationsCheck;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Health\Enums\Status;

uses(RefreshDatabase::class);

/**
 * @param  list<string>  $files
 * @param  list<string>  $ran
 */
function pendingMigrationsWith(array $files, array $ran, bool $repositoryExists = true): void
{
    $repository = Mockery::mock(MigrationRepositoryInterface::class);
    $repository->shouldReceive('getRan')->andReturn($ran);

    $migrator = Mockery::mock(Migrator::class);
    $migrator->shouldReceive('repositoryExists')->andReturn($repositoryExists);
    $migrator->shouldReceive('paths')->andReturn([]);
    $migrator->shouldReceive('getMigrationFiles')->andReturn(array_combine($files, $files));
    $migrator->shouldReceive('getRepository')->andReturn($repository);

    app()->instance('migrator', $migrator);
}

it('passes when every migration has run', function () {
    pendingMigrationsWith(['a', 'b'], ['a', 'b']);

    $result = (new PendingMigrationsCheck)->run();

    expect($result->status->equals(Status::ok()))->toBeTrue()
        ->and($result->shortSummary)->toBe('up to date');
})->group('unit', 'checks');

it('fails and names the migrations that have not run', function () {
    pendingMigrationsWith(['a', 'b', 'c'], ['a']);

    $result = (new PendingMigrationsCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue()
        ->and($result->shortSummary)->toBe('2 pending')
        ->and($result->getNotificationMessage())->toContain('b, c');
})->group('unit', 'checks');

it('lists at most five pending migrations', function () {
    pendingMigrationsWith(['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7'], []);

    expect((new PendingMigrationsCheck)->run()->getNotificationMessage())->toContain('and 2 more');
})->group('unit', 'checks');

it('fails when the migrations table does not exist', function () {
    pendingMigrationsWith([], [], repositoryExists: false);

    expect((new PendingMigrationsCheck)->run()->shortSummary)->toBe('no migrations table');
})->group('unit', 'checks');

it('fails when the migrations cannot be read', function () {
    $migrator = Mockery::mock(Migrator::class);
    $migrator->shouldReceive('repositoryExists')->andThrow(new RuntimeException('connection lost'));
    app()->instance('migrator', $migrator);

    $result = (new PendingMigrationsCheck)->run();

    expect($result->shortSummary)->toBe('unreadable')
        ->and($result->getNotificationMessage())->toContain('connection lost');
})->group('unit', 'checks');

it('reports ok with "no database" when the app has none', function () {
    config(['health.uses_database' => false]);

    expect((new PendingMigrationsCheck)->run()->shortSummary)->toBe('no database');
})->group('unit', 'checks');
