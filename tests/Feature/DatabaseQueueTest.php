<?php

declare(strict_types=1);

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

final class DatabaseQueueProbeJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function handle(): void
    {
        Cache::put('database-queue-probe', 'handled');
    }
}

it('migrates the tables the database queue driver needs', function (): void {
    expect(Schema::hasTable('jobs'))->toBeTrue()
        ->and(Schema::hasTable('job_batches'))->toBeTrue()
        ->and(Schema::hasTable('failed_jobs'))->toBeTrue();
});

it('uses the database queue connection by default', function (): void {
    expect(config('queue.default'))->toBe('database')
        ->and(config('queue.failed.table'))->toBe('failed_jobs');
});

it('lets a worker on the database connection push, pop and run a job', function (): void {
    config(['queue.default' => 'database']);

    Queue::connection('database')->push(new DatabaseQueueProbeJob);
    expect(DB::table('jobs')->count())->toBe(1);

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--quiet' => true]);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(Cache::get('database-queue-probe'))->toBe('handled');
});
