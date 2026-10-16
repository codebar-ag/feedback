<?php

declare(strict_types=1);

use App\Geo\CloudflareGeoLocator;
use App\Geo\GeoLocator;
use App\Geo\MaxMindGeoLocator;
use App\Geo\NullGeoLocator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;

it('binds the locator from config', function (string $source, string $class): void {
    config(['feedback.geo' => $source]);
    app()->forgetInstance(GeoLocator::class);

    expect(app(GeoLocator::class))->toBeInstanceOf($class);
})->with([
    ['none', NullGeoLocator::class],
    ['off', NullGeoLocator::class],
    ['cloudflare', CloudflareGeoLocator::class],
    ['maxmind', MaxMindGeoLocator::class],
]);

it('works without a MaxMind database file', function (): void {
    config(['feedback.geo' => 'maxmind', 'feedback.maxmind.database_path' => '/nonexistent.mmdb']);
    app()->forgetInstance(GeoLocator::class);

    expect(app(GeoLocator::class)->locate(new Request, '203.0.113.7'))->toBeNull();
});

it('schedules geoip:update only for maxmind', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e): bool => str_contains((string) $e->command, 'geoip:update'));

    config(['feedback.geo' => 'none']);
    expect($event)->not->toBeNull()->and($event->filtersPass(app()))->toBeFalse();

    config(['feedback.geo' => 'maxmind']);
    expect($event->filtersPass(app()))->toBeTrue();
});
