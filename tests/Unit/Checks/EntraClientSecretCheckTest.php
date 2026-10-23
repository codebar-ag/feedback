<?php

declare(strict_types=1);

use App\Checks\EntraClientSecretCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Enums\Status;

beforeEach(function () {
    config([
        'microsoft-entra-sso.tenant_id' => 'tenant',
        'microsoft-entra-sso.client_id' => 'client',
        'microsoft-entra-sso.client_secret' => 'secret',
        'services.microsoft' => [],
    ]);
});

it('passes when Entra issues a token', function () {
    Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'x'])]);

    expect((new EntraClientSecretCheck)->run()->status->equals(Status::ok()))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://login.microsoftonline.com/tenant/oauth2/v2.0/token');
})->group('unit', 'checks');

it('fails when Entra rejects the secret', function () {
    Http::fake(['login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401)]);

    $result = (new EntraClientSecretCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue()
        ->and($result->shortSummary)->toBe('invalid_client');
})->group('unit', 'checks');

it('reads socialite credentials from services.microsoft', function () {
    config([
        'microsoft-entra-sso' => [],
        'services.microsoft' => ['tenant' => 'social-tenant', 'client_id' => 'client', 'client_secret' => 'secret'],
    ]);
    Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'x'])]);

    expect((new EntraClientSecretCheck)->run()->status->equals(Status::ok()))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/social-tenant/'));
})->group('unit', 'checks');

it('passes without a request when no SSO is configured', function (?string $secret) {
    config(['microsoft-entra-sso.client_secret' => $secret]);
    Http::fake();

    $result = (new EntraClientSecretCheck)->run();

    expect($result->status->equals(Status::ok()))->toBeTrue()
        ->and($result->shortSummary)->toBe('no sso configured');
    Http::assertNothingSent();
})->with([null, '', 'not-configured'])->group('unit', 'checks');

it('reports the HTTP status when Entra gives no error code', function () {
    Http::fake(['login.microsoftonline.com/*' => Http::response('', 500)]);

    expect((new EntraClientSecretCheck)->run()->shortSummary)->toBe('http 500');
})->group('unit', 'checks');

it('fails when Entra cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $result = (new EntraClientSecretCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue()
        ->and($result->shortSummary)->toBe('entra unreachable');
})->group('unit', 'checks');
