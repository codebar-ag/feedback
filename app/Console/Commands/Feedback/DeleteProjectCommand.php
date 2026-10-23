<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Console\Commands\Feedback\Concerns\InteractsWithFeedback;
use Illuminate\Console\Command;

final class DeleteProjectCommand extends Command
{
    use InteractsWithFeedback;

    protected $signature = 'feedback:project:delete
        {key : Public key or ID}
        {--force : Delete without asking}
        {--by= : Name recorded in the activity log}
        {--json : Output as JSON}';

    protected $description = 'Delete a feedback project with all its feedback and screenshots';

    public function handle(): int
    {
        $this->recordActor();

        $project = $this->findProject($this->stringArgument('key'));

        if ($project === null) {
            return self::FAILURE;
        }

        $entries = $project->entries()->count();

        if (! $this->option('force') && ! $this->confirm("Delete [{$project->name}] and its {$entries} feedback entries? This cannot be undone.", false)) {
            $this->components->error('Not deleted. Confirm or pass --force.');

            return self::FAILURE;
        }

        // Entries and screenshots go with it through ON DELETE CASCADE.
        $project->delete();

        if ($this->option('json')) {
            return $this->printJson(['id' => $project->id, 'name' => $project->name, 'public_key' => $project->public_key, 'deleted_entries' => $entries]);
        }

        $this->components->info("Project [{$project->name}] deleted with {$entries} feedback entries. Its key no longer works.");

        return self::SUCCESS;
    }
}
