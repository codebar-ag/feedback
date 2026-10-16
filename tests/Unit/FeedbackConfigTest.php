<?php

declare(strict_types=1);
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\PostgresConnection;

const LIMIT_KEYS = ['FEEDBACK_LIMIT_PER_IP_PER_MINUTE', 'FEEDBACK_LIMIT_PER_PROJECT_PER_MINUTE', 'FEEDBACK_MIN_ELAPSED_MS'];

/**
 * Loads config/feedback.php with the given limit variables (null = unset).
 *
 * @param  array<string, string|null>  $env
 * @return array<string, int|null>
 */
function limitsWith(array $env): array
{
    $backup = [];

    foreach (LIMIT_KEYS as $key) {
        $backup[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        $value = $env[$key] ?? null;
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);

        if ($value !== null) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        /** @var array{limits: array<string, int|null>} $config */
        $config = require base_path('config/feedback.php');

        return $config['limits'];
    } finally {
        foreach ($backup as $key => [$env, $server, $put]) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
            if ($env !== null) {
                $_ENV[$key] = $env;
            }
            if ($server !== null) {
                $_SERVER[$key] = $server;
            }
            if ($put !== false) {
                putenv("{$key}={$put}");
            }
        }
    }
}

it('uses safe limit defaults when nothing is set', function (?string $value): void {
    $env = array_fill_keys(LIMIT_KEYS, $value);

    expect(limitsWith($env))->toBe(['per_ip_per_minute' => 10, 'per_project_per_minute' => 60, 'min_elapsed_ms' => 2000]);
})->with(['unset' => [null], 'empty' => ['']]);

it('takes limits from the environment', function (): void {
    expect(limitsWith([
        'FEEDBACK_LIMIT_PER_IP_PER_MINUTE' => '3',
        'FEEDBACK_LIMIT_PER_PROJECT_PER_MINUTE' => '100',
        'FEEDBACK_MIN_ELAPSED_MS' => '1500',
    ]))->toBe(['per_ip_per_minute' => 3, 'per_project_per_minute' => 100, 'min_elapsed_ms' => 1500]);
});

it('switches a limit off with 0', function (): void {
    expect(limitsWith(array_fill_keys(LIMIT_KEYS, '0')))
        ->toBe(['per_ip_per_minute' => null, 'per_project_per_minute' => null, 'min_elapsed_ms' => null]);
});

it('falls back to the default for invalid values', function (): void {
    expect(limitsWith(['FEEDBACK_LIMIT_PER_IP_PER_MINUTE' => 'many'])['per_ip_per_minute'])->toBe(10);
});

it('supports a read replica as configured by Laravel Cloud', function (): void {
    config([
        'database.connections.replica' => [...config('database.connections.pgsql'), 'read' => ['host' => 'replica.internal']],
    ]);

    $factory = app(ConnectionFactory::class);
    $connection = $factory->make(config('database.connections.replica'), 'replica');

    expect($connection)->toBeInstanceOf(PostgresConnection::class)
        ->and(config('database.connections.pgsql.write.host'))->toBe(config('database.connections.pgsql.host'));
});
