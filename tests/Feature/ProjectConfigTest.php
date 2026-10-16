<?php

declare(strict_types=1);

use App\Models\FeedbackProject;

it('returns the project color', function (): void {
    $project = FeedbackProject::factory()->create(['theme' => ['primary' => '#0A7C66'], 'allowed_origins' => [TEST_ORIGIN]]);

    test()->getJson("/v1/projects/{$project->public_key}/config", ['Origin' => TEST_ORIGIN])
        ->assertOk()
        ->assertExactJson(['theme' => ['primary' => '#0A7C66']])
        ->assertHeader('Access-Control-Allow-Origin', TEST_ORIGIN)
        ->assertHeader('Cache-Control', 'max-age=600, public');
});

it('falls back to the default color', function (?string $default, string $expected): void {
    config(['feedback.default_color' => $default]);
    $project = FeedbackProject::factory()->create(['theme' => null, 'allowed_origins' => [TEST_ORIGIN]]);

    test()->getJson("/v1/projects/{$project->public_key}/config", ['Origin' => TEST_ORIGIN])
        ->assertOk()
        ->assertExactJson(['theme' => ['primary' => $expected]]);
})->with([
    ['black', '#000000'],
    ['white', '#FFFFFF'],
    ['#ED813A', '#ED813A'],
    ['invalid', '#000000'],
    [null, '#000000'],
]);

it('applies the same origin and IP checks as sending', function (): void {
    $project = FeedbackProject::factory()->create(['allowed_origins' => [TEST_ORIGIN], 'allowed_ips' => ['10.0.0.0/8']]);
    $url = "/v1/projects/{$project->public_key}/config";

    test()->getJson($url, ['Origin' => 'https://evil.example'])->assertForbidden();
    test()->getJson($url)->assertForbidden();
    test()->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->getJson($url, ['Origin' => TEST_ORIGIN])->assertForbidden();
    test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->getJson($url, ['Origin' => TEST_ORIGIN])->assertOk();
});

it('hides inactive projects', function (): void {
    $project = FeedbackProject::factory()->inactive()->create(['allowed_origins' => [TEST_ORIGIN]]);

    test()->getJson("/v1/projects/{$project->public_key}/config", ['Origin' => TEST_ORIGIN])
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'project_not_found']]);
});
