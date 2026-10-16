<?php

declare(strict_types=1);

use App\Models\FeedbackProject;

it('reports health without details', function (): void {
    test()->getJson('/up')->assertOk()->assertExactJson(['status' => 'ok']);
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

it('renders unknown routes as JSON 404', function (): void {
    test()->get('/')->assertNotFound()->assertExactJson(['error' => ['code' => 'not_found']]);
    test()->get('/api/feedback')->assertNotFound();
});

it('renders wrong methods as JSON 405', function (): void {
    $project = FeedbackProject::factory()->create();

    test()->delete("/v1/projects/{$project->public_key}/feedback")
        ->assertStatus(405)
        ->assertExactJson(['error' => ['code' => 'method_not_allowed']]);
});

it('exposes no web or auth routes', function (): void {
    $uris = collect(app('router')->getRoutes()->getRoutes())->map->uri()->sort()->values()->all();

    expect($uris)->toBe(['up', 'v1/projects/{publicKey}/config', 'v1/projects/{publicKey}/feedback', 'v1/projects/{publicKey}/feedback']);
});
