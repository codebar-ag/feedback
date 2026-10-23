<?php

declare(strict_types=1);

use App\Checks\DatabaseAvailability;

it('treats the database as available by default', function () {
    config(['health.uses_database' => null]);

    expect(DatabaseAvailability::isAvailable())->toBeTrue();
})->group('unit', 'checks');

it('treats the database as unavailable when the app opts out', function () {
    config(['health.uses_database' => false]);

    expect(DatabaseAvailability::isAvailable())->toBeFalse();
})->group('unit', 'checks');
