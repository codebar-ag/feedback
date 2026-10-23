<?php

declare(strict_types=1);

use App\Checks\DatabaseConnectionCountCheck;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\MySqlConnection;
use Spatie\Health\Enums\Status;

function connectionCountCheckWith(int $connections): DatabaseConnectionCountCheck
{
    config(['health.uses_database' => true, 'database.connections.health_pgsql' => ['driver' => 'pgsql']]);

    $check = Mockery::mock(DatabaseConnectionCountCheck::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $check->shouldReceive('getConnectionCount')->andReturn($connections);
    $check->connectionName('health_pgsql');

    return $check;
}

function bindConnection(string $class, string $driver, mixed $count): void
{
    $connection = Mockery::mock($class);
    $connection->shouldReceive('getDriverName')->andReturn($driver);
    $connection->shouldReceive('scalar')->andReturn($count);
    $connection->shouldReceive('selectOne')->andReturn((object) ['COUNT(*)' => $count]);

    $resolver = Mockery::mock(ConnectionResolverInterface::class);
    $resolver->shouldReceive('connection')->andReturn($connection);

    app()->instance(ConnectionResolverInterface::class, $resolver);
}

it('reports ok with "no database" when the app has none', function () {
    config(['health.uses_database' => false]);

    $result = (new DatabaseConnectionCountCheck)->run();

    expect($result->status->equals(Status::ok()))->toBeTrue()
        ->and($result->shortSummary)->toBe('no database');
})->group('unit', 'checks');

it('reports ok for sqlite, which has no connection count', function () {
    config(['health.uses_database' => true, 'database.connections.health_sqlite' => ['driver' => 'sqlite']]);

    $result = (new DatabaseConnectionCountCheck)->connectionName('health_sqlite')->run();

    expect($result->status->equals(Status::ok()))->toBeTrue()
        ->and($result->shortSummary)->toBe('sqlite');
})->group('unit', 'checks');

it('only reports the count and never warns or fails', function (int $connections, string $summary) {
    $result = connectionCountCheckWith($connections)->run();

    expect($result->status->equals(Status::ok()))->toBeTrue()
        ->and($result->shortSummary)->toBe($summary)
        ->and($result->meta)->toBe(['connection_count' => $connections]);
})->with([
    'one' => [1, '1 connection'],
    'many' => [500, '500 connections'],
])->group('unit', 'checks');

it('counts only the connections of the current database on postgres', function () {
    config(['database.connections.health_pgsql' => ['driver' => 'pgsql']]);
    bindConnection(Connection::class, 'pgsql', 7);

    $check = new class extends DatabaseConnectionCountCheck
    {
        public function count(): int
        {
            return $this->getConnectionCount();
        }
    };

    expect($check->connectionName('health_pgsql')->count())->toBe(7);
})->group('unit', 'checks');

it('reports zero when postgres returns no number', function () {
    config(['database.connections.health_pgsql' => ['driver' => 'pgsql']]);
    bindConnection(Connection::class, 'pgsql', null);

    $check = new class extends DatabaseConnectionCountCheck
    {
        public function count(): int
        {
            return $this->getConnectionCount();
        }
    };

    expect($check->connectionName('health_pgsql')->count())->toBe(0);
})->group('unit', 'checks');

it('falls back to spatie for other drivers', function () {
    config(['database.connections.health_mysql' => ['driver' => 'mysql']]);
    bindConnection(MySqlConnection::class, 'mysql', 4);

    $check = new class extends DatabaseConnectionCountCheck
    {
        public function count(): int
        {
            return $this->getConnectionCount();
        }
    };

    expect($check->connectionName('health_mysql')->count())->toBe(4);
})->group('unit', 'checks');
