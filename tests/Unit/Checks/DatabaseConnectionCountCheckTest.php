<?php

declare(strict_types=1);

use App\Checks\DatabaseConnectionCountCheck;
use Spatie\Health\Enums\Status;

function connectionCountCheckWith(int $connections): DatabaseConnectionCountCheck
{
    config(['health.uses_database' => true, 'database.connections.health_pgsql' => ['driver' => 'pgsql']]);

    $check = Mockery::mock(DatabaseConnectionCountCheck::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $check->shouldReceive('getConnectionCount')->andReturn($connections);
    $check->connectionName('health_pgsql');

    return $check;
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

it('applies the agreed thresholds', function (int $connections, Status $status) {
    expect(connectionCountCheckWith($connections)->run()->status->equals($status))->toBeTrue();
})->with([
    'ok' => [10, Status::ok()],
    'warning above 50' => [60, Status::warning()],
    'failed above 80' => [90, Status::failed()],
])->group('unit', 'checks');
