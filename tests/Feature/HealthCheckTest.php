<?php

declare(strict_types=1);

use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Facades\Health;

beforeEach(function (): void {
    // Deterministic checks: no network (security advisories) in tests.
    Health::clearChecks()->checks([DatabaseCheck::new()]);
});

it('serves fresh results to Oh Dear with the secret', function (): void {
    $response = test()->getJson('/oh-dear-health-check-results', ['oh-dear-health-check-secret' => 'test-secret'])->assertOk();

    expect(collect($response->json('checkResults'))->pluck('name')->all())->toEqualCanonicalizing(['Database'])
        ->and(collect($response->json('checkResults'))->pluck('status')->unique()->all())->toBe(['ok']);
});

it('rejects Oh Dear requests without the secret', function (?string $secret): void {
    $headers = $secret === null ? [] : ['oh-dear-health-check-secret' => $secret];

    expect(test()->getJson('/oh-dear-health-check-results', $headers)->status())->toBeIn([401, 403]);
})->with([null, 'wrong']);

it('registers the production checks and schedules health:check', function (): void {
    $this->refreshApplication();

    expect(collect(Health::registeredChecks())->map(fn ($c): string => class_basename($c))->all())
        ->toContain(
            'DatabaseCheck',
            'DebugModeCheck',
            'EnvironmentCheck',
            'SecurityAdvisoriesCheck',
            'ScheduleCheck',
            'CacheCheck',
            'OptimizedAppCheck',
            'JobsCheck',
            'FailedJobsCheck',
            'PendingMigrationsCheck',
            'ProductionConfigCheck',
            'SchedulePolicyCheck',
        );

    test()->artisan('schedule:list')->expectsOutputToContain('health:check')->assertExitCode(0);
});

it('answers the uptime check on /up', function (): void {
    test()->get('/up')->assertOk();
});

it('runs health:check from the console', function (): void {
    test()->artisan('health:check')->assertExitCode(0);
});
