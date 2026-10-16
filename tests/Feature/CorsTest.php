<?php

declare(strict_types=1);

use App\Models\FeedbackProject;

beforeEach(function (): void {
    $this->project = FeedbackProject::factory()->create(['allowed_origins' => [TEST_ORIGIN, 'https://*.beispiel.ch']]);
    $this->url = "/v1/projects/{$this->project->public_key}/feedback";
});

it('answers an allowed preflight with 204 and CORS headers', function (string $origin): void {
    test()->call('OPTIONS', $this->url, server: [
        'HTTP_ORIGIN' => $origin,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'idempotency-key',
    ])
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Idempotency-Key')
        ->assertHeader('Access-Control-Expose-Headers', 'Retry-After')
        ->assertHeader('Access-Control-Max-Age', '600')
        ->assertHeader('Vary', 'Origin')
        ->assertHeaderMissing('Access-Control-Allow-Credentials');
})->with([TEST_ORIGIN, 'https://app.beispiel.ch']);

it('rejects a foreign preflight without CORS headers', function (?string $origin): void {
    $server = $origin === null ? [] : ['HTTP_ORIGIN' => $origin];

    test()->call('OPTIONS', $this->url, server: [...$server, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST'])
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'origin_not_allowed']])
        ->assertHeader('Vary', 'Origin')
        ->assertHeaderMissing('Access-Control-Allow-Origin');
})->with(['https://evil.example', 'https://beispiel.ch', null]);

it('answers a preflight for an unknown project with 404', function (): void {
    test()->call('OPTIONS', '/v1/projects/fbk_live_unknownAAAAAAAAAAAAAAA/feedback', server: ['HTTP_ORIGIN' => TEST_ORIGIN])
        ->assertNotFound()
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('never answers with a wildcard origin even for public projects', function (): void {
    $this->project->update(['allowed_origins' => ['*']]);

    test()->call('OPTIONS', $this->url, server: ['HTTP_ORIGIN' => 'https://irgendwo.example'])
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'https://irgendwo.example');
});
