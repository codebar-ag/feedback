<?php

declare(strict_types=1);

use App\Checks\SchedulePolicyCheck;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\Health\Enums\Status;

function schedulePolicyWith(string $expression): Schedule
{
    $schedule = new Schedule;
    $schedule->command('inspire')->cron($expression);
    app()->instance(Schedule::class, $schedule);

    return $schedule;
}

it('passes for hourly and five-minute tasks', function (string $expression) {
    schedulePolicyWith($expression);

    expect((new SchedulePolicyCheck)->run()->status->equals(Status::ok()))->toBeTrue();
})->with(['0 * * * *', '*/5 * * * 1-5', '0 22 28-31 * *', '0,30 * * * *'])->group('unit', 'checks');

it('fails for tasks that run more often than every five minutes', function (string $expression) {
    schedulePolicyWith($expression);

    expect((new SchedulePolicyCheck)->run()->status->equals(Status::failed()))->toBeTrue();
})->with(['* * * * *', '*/2 * * * *', '0,1,2,3,4,5,6,7,8,9,10,11,12 * * * *'])->group('unit', 'checks');

it('passes the schedule this app defines', function () {
    $this->artisan('schedule:list')->assertSuccessful();

    expect((new SchedulePolicyCheck)->run()->status->equals(Status::ok()))->toBeTrue();
})->group('unit', 'checks');
