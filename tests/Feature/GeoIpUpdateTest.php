<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

function geoArchive(bool $withDatabase = true): string
{
    $dir = sys_get_temp_dir().'/geo-'.uniqid();
    mkdir($dir.'/GeoLite2-City_20261001', 0777, true);
    file_put_contents($dir.'/GeoLite2-City_20261001/'.($withDatabase ? 'GeoLite2-City.mmdb' : 'README.txt'), 'MMDB-CONTENT');

    $tar = new PharData($dir.'/db.tar');
    $tar->buildFromDirectory($dir, '/GeoLite2/');
    $tar->compress(Phar::GZ);

    return (string) file_get_contents($dir.'/db.tar.gz');
}

beforeEach(function (): void {
    $this->target = sys_get_temp_dir().'/geoip-test-'.uniqid().'/GeoLite2-City.mmdb';
    config([
        'feedback.maxmind.account_id' => '123',
        'feedback.maxmind.license_key' => 'license',
        'feedback.maxmind.database_path' => $this->target,
    ]);
});

it('downloads and installs the GeoLite2 database', function (): void {
    $archive = geoArchive();
    Http::fake(['download.maxmind.com/*' => fn () => Http::response($archive)]);

    Artisan::call('geoip:update', ['--json' => true]);

    expect(json_decode(Artisan::output(), true))->toMatchArray(['status' => 'updated', 'path' => $this->target])
        ->and(file_get_contents($this->target))->toBe('MMDB-CONTENT');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'GeoLite2-City/download')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('123:license')));

    test()->artisan('geoip:update')->expectsOutputToContain('GeoIP-Datenbank aktualisiert')->assertExitCode(0);
});

it('fails without credentials', function (): void {
    config(['feedback.maxmind.license_key' => null]);

    test()->artisan('geoip:update')->expectsOutputToContain('MAXMIND_LICENSE_KEY')->assertExitCode(2);
    test()->artisan('geoip:update', ['--json' => true])->expectsOutputToContain('"status":"error"')->assertExitCode(2);
});

it('fails on invalid configuration', function (): void {
    config(['feedback.maxmind.edition' => null]);

    test()->artisan('geoip:update')->expectsOutputToContain('Invalid feedback.maxmind')->assertExitCode(2);
});

it('fails on download errors', function (): void {
    Http::fake(['*' => Http::response('denied', 401)]);

    test()->artisan('geoip:update')->expectsOutputToContain('HTTP 401')->assertExitCode(1);
});

it('fails on archives without database', function (): void {
    Http::fake(['*' => Http::response(geoArchive(false))]);

    test()->artisan('geoip:update')->expectsOutputToContain('no .mmdb')->assertExitCode(1);
});

it('fails on broken archives', function (): void {
    Http::fake(['*' => Http::response('not a tarball')]);

    test()->artisan('geoip:update')->expectsOutputToContain('Update failed')->assertExitCode(1);
});
