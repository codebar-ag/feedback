<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Console\Commands\Feedback\Concerns\InteractsWithFeedback;
use App\Support\FeedbackLink;
use Illuminate\Console\Command;

final class LinkFeedbackCommand extends Command
{
    use InteractsWithFeedback;

    protected $signature = 'feedback:link
        {id : Feedback ID}
        {--expires='.FeedbackLink::DEFAULT_MINUTES.' : Minutes until the link expires (max '.FeedbackLink::MAX_MINUTES.' = 30 days)}
        {--read-only : Without the buttons to complete and reopen}
        {--by= : Name recorded in the activity log for status changes made with this link}
        {--json : Output as JSON}';

    protected $description = 'Create a signed, expiring link to the feedback page (details, screenshot, complete/reopen)';

    public function handle(): int
    {
        $minutes = $this->stringOption('expires') ?? (string) FeedbackLink::DEFAULT_MINUTES;

        if (! ctype_digit($minutes) || (int) $minutes < 1 || (int) $minutes > FeedbackLink::MAX_MINUTES) {
            $this->components->error('--expires must be between 1 and '.FeedbackLink::MAX_MINUTES.' minutes.');

            return self::INVALID;
        }

        $entry = $this->findEntry($this->stringArgument('id'));

        if ($entry === null) {
            return self::FAILURE;
        }

        $expires = now()->addMinutes((int) $minutes)->startOfSecond();
        $hasScreenshot = $entry->screenshot()->exists();
        $manage = ! $this->option('read-only');
        $url = FeedbackLink::page($entry, $expires, $manage, $this->stringOption('by'));
        $screenshotUrl = $hasScreenshot ? FeedbackLink::screenshot($entry, $expires, download: true) : null;

        if ($this->option('json')) {
            return $this->printJson([
                'id' => $entry->id,
                'url' => $url,
                'screenshot_url' => $screenshotUrl,
                'manage' => $manage,
                'expires_at' => $expires->toIso8601String(),
            ]);
        }

        $this->components->twoColumnDetail('Expires', $expires->toDateTimeString().' UTC');
        $this->components->twoColumnDetail('Screenshot', $hasScreenshot ? 'yes' : 'none');
        $this->components->twoColumnDetail('Status changes', $manage ? 'allowed' : 'read-only');
        $this->newLine();
        $this->line($url);
        $this->newLine();
        $this->components->warn($manage
            ? 'Anyone with this link can see the feedback and complete or reopen it until it expires.'
            : 'Anyone with this link can see the feedback until it expires.');

        return self::SUCCESS;
    }
}
