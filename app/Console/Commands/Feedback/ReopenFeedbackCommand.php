<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Console\Commands\Feedback\Concerns\InteractsWithFeedback;
use Illuminate\Console\Command;

final class ReopenFeedbackCommand extends Command
{
    use InteractsWithFeedback;

    protected $signature = 'feedback:reopen {id* : One or more feedback IDs} {--by= : Name recorded in the activity log}';

    protected $description = 'Reopen completed feedback entries';

    public function handle(): int
    {
        $this->recordActor();
        $status = self::SUCCESS;

        foreach ((array) $this->argument('id') as $id) {
            $entry = $this->findEntry((string) $id);

            if ($entry === null) {
                $status = self::FAILURE;

                continue;
            }

            if ($entry->completed_at !== null) {
                $entry->completed_at = null;
                $entry->save();
            }

            $this->components->twoColumnDetail($entry->id, 'open');
        }

        return $status;
    }
}
