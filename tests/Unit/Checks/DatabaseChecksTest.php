<?php

declare(strict_types=1);

use App\Checks\DatabaseCheck;
use App\Checks\DatabaseConnectionCountCheck;
use App\Checks\FailedJobsCheck;
use App\Checks\JobsCheck;
use App\Checks\PendingMigrationsCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Health\Checks\Check;
use Spatie\Health\Enums\Status;

uses(RefreshDatabase::class);

it('reports ok with "no database" when the app has none', function (string $check) {
    config(['health.uses_database' => false]);

    /** @var Check $instance */
    $instance = new $check;
    $result = $instance->run();

    expect($result->status->equals(Status::ok()))->toBeTrue()
        ->and($result->shortSummary)->toBe('no database');
})->with([DatabaseCheck::class, DatabaseConnectionCountCheck::class, JobsCheck::class, FailedJobsCheck::class, PendingMigrationsCheck::class])->group('unit', 'checks');

it('checks the database when the app has one', function () {
    expect((new DatabaseCheck)->run()->status->equals(Status::ok()))->toBeTrue();
})->group('unit', 'checks');

it('uses the agreed connection thresholds', function () {
    $check = new DatabaseConnectionCountCheck;

    expect((fn () => [$this->warningThreshold, $this->errorThreshold])->call($check))->toBe([50, 80]);
})->group('unit', 'checks');
