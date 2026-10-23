<?php

declare(strict_types=1);

namespace App\Checks;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Fails when a scheduled task runs more often than every five minutes.
 * Laravel Cloud wakes a hibernating app for every due cron, so a task
 * every minute keeps the app awake around the clock. Filters such as
 * `->between()` or `->environments()` do not help: Cloud only sees the cron.
 */
final class SchedulePolicyCheck extends Check
{
    public const int MIN_INTERVAL_MINUTES = 5;

    public function run(): Result
    {
        $offenders = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => $this->runsMoreOftenThanAllowed($event->expression))
            ->map(fn (Event $event): string => trim(($event->description ?: (string) $event->command)." ({$event->expression})"))
            ->values()
            ->all();

        $result = Result::make()->shortSummary($offenders === [] ? 'ok' : count($offenders).' task(s) too frequent');

        return $offenders === []
            ? $result->ok()
            : $result->failed('Tasks run more often than every '.self::MIN_INTERVAL_MINUTES.' minutes: '.implode(', ', $offenders).'.');
    }

    private function runsMoreOftenThanAllowed(string $expression): bool
    {
        $minute = explode(' ', trim($expression))[0];

        if ($minute === '*') {
            return true;
        }

        if (preg_match('/^\*\/(\d+)$/', $minute, $matches) === 1) {
            return (int) $matches[1] < self::MIN_INTERVAL_MINUTES;
        }

        return count(explode(',', $minute)) > intdiv(60, self::MIN_INTERVAL_MINUTES);
    }
}
