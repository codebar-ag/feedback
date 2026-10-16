<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Console\Commands\Feedback\Concerns\InteractsWithFeedback;
use Illuminate\Console\Command;

final class NoteFeedbackCommand extends Command
{
    use InteractsWithFeedback;

    protected $signature = 'feedback:note {id : Feedback ID} {note? : Internal note} {--clear : Remove the note} {--by= : Name recorded in the activity log}';

    protected $description = 'Set or clear the internal note of a feedback entry';

    public function handle(): int
    {
        $this->recordActor();

        $entry = $this->findEntry($this->stringArgument('id'));

        if ($entry === null) {
            return self::FAILURE;
        }

        $note = trim($this->stringArgument('note'));

        if (! $this->option('clear') && $note === '') {
            $this->components->error('Pass a note or --clear.');

            return self::INVALID;
        }

        $entry->internal_note = $this->option('clear') ? null : $note;
        $entry->save();

        $this->components->info($entry->internal_note === null ? 'Notiz entfernt.' : 'Notiz gespeichert.');

        return self::SUCCESS;
    }
}
