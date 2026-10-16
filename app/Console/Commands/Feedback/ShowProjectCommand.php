<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Console\Commands\Feedback\Concerns\InteractsWithFeedback;
use Illuminate\Console\Command;

final class ShowProjectCommand extends Command
{
    use InteractsWithFeedback;

    protected $signature = 'feedback:project:show {key : Public key or ID} {--json : Output as JSON}';

    protected $description = 'Show a feedback project with origins, IPs, color and snippet';

    public function handle(): int
    {
        $project = $this->findProject($this->stringArgument('key'));

        if ($project === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            return $this->printJson($this->projectData($project));
        }

        $this->printProject($project);
        $this->components->twoColumnDetail('Offen / Erledigt', $project->entries()->open()->count().' / '.$project->entries()->completed()->count());

        return self::SUCCESS;
    }
}
