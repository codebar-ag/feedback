<?php

declare(strict_types=1);

use App\Checks\DatabaseCheck;
use Spatie\Health\Enums\Status;

it('reports ok with "no database" when the app has none', function () {
    config(['health.uses_database' => false]);

    $result = (new DatabaseCheck)->run();

    expect($result->status->equals(Status::ok()))->toBeTrue()
        ->and($result->shortSummary)->toBe('no database');
})->group('unit', 'checks');

it('connects to the database when the app has one', function () {
    config(['health.uses_database' => true]);

    expect((new DatabaseCheck)->run()->status->equals(Status::ok()))->toBeTrue();
})->group('unit', 'checks');
