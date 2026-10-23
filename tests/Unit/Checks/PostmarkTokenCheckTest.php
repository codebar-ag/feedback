<?php

declare(strict_types=1);

use App\Checks\PostmarkTokenCheck;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Enums\Status;

beforeEach(function () {
    config(['mail.default' => 'postmark']);
});

it('passes when Postmark accepts the server token', function () {
    config(['services.postmark.token' => 'token']);
    Http::fake(['api.postmarkapp.com/server' => Http::response(['Name' => 'app'])]);

    expect((new PostmarkTokenCheck)->run()->status->equals(Status::ok()))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Postmark-Server-Token', 'token'));
})->group('unit', 'checks');

it('fails when Postmark rejects the server token', function () {
    config(['services.postmark.token' => 'token']);
    Http::fake(['api.postmarkapp.com/server' => Http::response(['Message' => 'Invalid token'], 401)]);

    expect((new PostmarkTokenCheck)->run()->status->equals(Status::failed()))->toBeTrue();
})->group('unit', 'checks');

it('fails without a token', function () {
    config(['services.postmark.token' => null, 'services.postmark.key' => null]);
    Http::fake();

    expect((new PostmarkTokenCheck)->run()->status->equals(Status::failed()))->toBeTrue();
    Http::assertNothingSent();
})->group('unit', 'checks');

it('passes without a request when the mailer is not postmark', function () {
    config(['mail.default' => 'log', 'services.postmark.token' => null]);
    Http::fake();

    expect((new PostmarkTokenCheck)->run()->status->equals(Status::ok()))->toBeTrue();
    Http::assertNothingSent();
})->group('unit', 'checks');
