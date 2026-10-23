<?php

declare(strict_types=1);

use App\Checks\ProductionConfigCheck;
use Spatie\Health\Enums\Status;

beforeEach(function () {
    config([
        'app.url' => 'https://example.ch',
        'cache.default' => 'database',
        'session.driver' => 'database',
    ]);
});

it('passes for a production-ready configuration', function () {
    expect((new ProductionConfigCheck)->run()->status->equals(Status::ok()))->toBeTrue();
})->group('unit', 'checks');

it('fails when APP_URL is not https', function () {
    config(['app.url' => 'http://example.ch']);

    expect((new ProductionConfigCheck)->run()->status->equals(Status::failed()))->toBeTrue();
})->group('unit', 'checks');

it('fails when APP_URL points at localhost', function () {
    config(['app.url' => 'https://localhost']);

    expect((new ProductionConfigCheck)->run()->status->equals(Status::failed()))->toBeTrue();
})->group('unit', 'checks');

it('fails for array cache or session stores', function (string $key) {
    config([$key => 'array']);

    expect((new ProductionConfigCheck)->run()->status->equals(Status::failed()))->toBeTrue();
})->with(['cache.default', 'session.driver'])->group('unit', 'checks');

it('does not check the mailer', function () {
    config(['mail.default' => 'log']);

    expect((new ProductionConfigCheck)->run()->status->equals(Status::ok()))->toBeTrue();
})->group('unit', 'checks');
