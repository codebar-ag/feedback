<?php

declare(strict_types=1);

use App\Checks\JobsCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Spatie\Health\Enums\Status;

uses(RefreshDatabase::class);

it('returns ok when jobs table is empty', function () {
    Sleep::fake();

    DB::table('jobs')->delete();

    $result = (new JobsCheck)->run();

    expect($result->status->equals(Status::ok()))->toBeTrue();
})->group('unit', 'checks');

it('returns failed when multiple jobs remain stable', function () {
    Sleep::fake();

    DB::table('jobs')->delete();
    $row = [
        'queue' => 'default',
        'payload' => json_encode(['job' => 'x']),
        'attempts' => 0,
        'available_at' => time(),
        'created_at' => time(),
    ];
    DB::table('jobs')->insert([$row, $row]);

    $result = (new JobsCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue();

    DB::table('jobs')->delete();
})->group('unit', 'checks');

it('returns failed when exactly one job stays queued', function () {
    Sleep::fake();

    DB::table('jobs')->delete();
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['job' => 'x']),
        'attempts' => 0,
        'available_at' => time(),
        'created_at' => time(),
    ]);

    $result = (new JobsCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue();

    DB::table('jobs')->delete();
})->group('unit', 'checks');

it('returns ok when job count drops during polling', function () {
    Sleep::fake();

    $check = new class extends JobsCheck
    {
        private int $polls = 0;

        protected function currentJobsTableCount(): int
        {
            $this->polls++;

            return $this->polls === 1 ? 2 : 1;
        }
    };

    $result = $check->run();

    expect($result->status->equals(Status::ok()))->toBeTrue();
})->group('unit', 'checks');

it('reports ok with "no database" when the app has none', function () {
    config(['health.uses_database' => false]);

    $result = (new JobsCheck)->run();

    expect($result->status->equals(Status::ok()))->toBeTrue()
        ->and($result->shortSummary)->toBe('no database');
})->group('unit', 'checks');
