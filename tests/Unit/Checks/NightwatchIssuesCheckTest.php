<?php

declare(strict_types=1);

use App\Checks\NightwatchIssuesCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Enums\Status;

beforeEach(function () {
    config([
        'default.nightwatch.url' => 'https://nightwatch.test',
        'default.nightwatch.api_token' => 'token',
        'default.nightwatch.application_id' => 'a30a8680-696a-479e-9acb-60fc75729636',
        'default.nightwatch.environment_id' => null,
    ]);
});

function fakeNightwatchListIssues(string $text, bool $isError = false): void
{
    Http::fake(['nightwatch.test/mcp' => Http::response([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['content' => [['type' => 'text', 'text' => $text]], 'isError' => $isError],
    ])]);
}

it('returns ok when nightwatch has no open issues', function () {
    fakeNightwatchListIssues('No issues found for this application (status: open).');

    $result = (new NightwatchIssuesCheck)->run();

    expect($result->status->equals(Status::ok()))->toBeTrue()
        ->and($result->shortSummary)->toBe('open nightwatch issues: 0');
})->group('unit', 'checks');

it('returns failed when nightwatch has open issues', function () {
    fakeNightwatchListIssues("## Issues for portal.vetter.ch\n\nShowing 2 open issues.\n\n### #4 Exception: Boom\n\n- **Type:** Exception\n\n### #3 Exception: Bang\n");

    $result = (new NightwatchIssuesCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue()
        ->and($result->shortSummary)->toBe('open nightwatch issues: 2');
})->group('unit', 'checks');

it('asks for open issues of the configured application and environment', function () {
    config(['default.nightwatch.environment_id' => 'a30a8680-70ae-4747-a833-b782de26b011']);
    fakeNightwatchListIssues('No issues found for this application (status: open).');

    (new NightwatchIssuesCheck)->run();

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer token')
        && $request['method'] === 'tools/call'
        && $request['params']['name'] === 'list_issues'
        && $request['params']['arguments'] === [
            'application_id' => 'a30a8680-696a-479e-9acb-60fc75729636',
            'environment_id' => 'a30a8680-70ae-4747-a833-b782de26b011',
            'status' => 'open',
        ]);
})->group('unit', 'checks');

it('fails with a hint when the token is invalid', function () {
    Http::fake(['nightwatch.test/mcp' => Http::response(status: 401)]);

    $result = (new NightwatchIssuesCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue()
        ->and($result->notificationMessage)->toContain('nightwatch:login');
})->group('unit', 'checks');

it('fails when nightwatch returns a tool error', function () {
    fakeNightwatchListIssues('Application not found.', isError: true);

    $result = (new NightwatchIssuesCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue()
        ->and($result->notificationMessage)->toContain('Application not found.');
})->group('unit', 'checks');

it('fails when nightwatch is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $result = (new NightwatchIssuesCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue()
        ->and($result->shortSummary)->toBe('nightwatch unreachable');
})->group('unit', 'checks');
