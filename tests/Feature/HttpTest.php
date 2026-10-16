<?php

declare(strict_types=1);

use App\Models\FeedbackProject;

it('reports health without details', function (): void {
    test()->getJson('/up')->assertOk()->assertExactJson(['status' => 'ok'])->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('reports a failing database as down', function (): void {
    $default = config('database.default');
    config([
        'database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/dir/db.sqlite'],
        'database.default' => 'broken',
    ]);

    try {
        test()->getJson('/up')->assertStatus(503)->assertExactJson(['status' => 'down']);
    } finally {
        config(['database.default' => $default]);
    }
});

it('blocks unknown paths with an empty 403', function (string $path): void {
    test()->get($path)->assertForbidden()->assertContent('')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with(['/', '/api/feedback', '/index.php', '/.env']);

it('blocks every browser navigation, also to existing routes', function (string $path): void {
    $project = FeedbackProject::factory()->create(['allowed_origins' => [TEST_ORIGIN]]);
    $path = str_replace('{key}', $project->public_key, $path);

    test()->get($path, ['Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Dest' => 'document'])->assertForbidden()->assertContent('');
    test()->get($path, ['Sec-Fetch-Dest' => 'document'])->assertForbidden();
})->with(['/', '/up', '/v1/projects/{key}/config', '/oh-dear-health-check-results']);

it('keeps the widget and monitoring working', function (): void {
    $project = FeedbackProject::factory()->create(['allowed_origins' => [TEST_ORIGIN]]);

    test()->get("/v1/projects/{$project->public_key}/config", ['Origin' => TEST_ORIGIN, 'Sec-Fetch-Mode' => 'cors', 'Sec-Fetch-Dest' => 'empty'])->assertOk();
    test()->getJson('/up')->assertOk();
});

it('renders wrong methods as JSON 405', function (): void {
    $project = FeedbackProject::factory()->create();

    test()->delete("/v1/projects/{$project->public_key}/feedback")
        ->assertStatus(405)
        ->assertExactJson(['error' => ['code' => 'method_not_allowed']]);
});

it('exposes no web or auth routes', function (): void {
    $uris = collect(app('router')->getRoutes()->getRoutes())->map->uri()->sort()->values()->all();

    expect($uris)->toBe(['oh-dear-health-check-results', 'up', 'v1/projects/{publicKey}/config', 'v1/projects/{publicKey}/feedback', 'v1/projects/{publicKey}/feedback']);
});

it('seeds without errors (deploys run migrate --seed)', function (): void {
    test()->artisan('db:seed', ['--force' => true])->assertExitCode(0);
    test()->artisan('migrate', ['--seed' => true, '--force' => true])->assertExitCode(0);
});
