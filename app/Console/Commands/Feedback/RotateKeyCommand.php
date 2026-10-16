<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Actions\RotatePublicKey;
use App\Console\Commands\Feedback\Concerns\InteractsWithFeedback;
use Illuminate\Console\Command;

final class RotateKeyCommand extends Command
{
    use InteractsWithFeedback;

    protected $signature = 'feedback:project:rotate-key {key : Public key or ID} {--by= : Name recorded in the activity log} {--json : Output as JSON}';

    protected $description = 'Replace the public key of a feedback project';

    public function handle(RotatePublicKey $rotate): int
    {
        $this->recordActor();

        $project = $this->findProject($this->stringArgument('key'));

        if ($project === null) {
            return self::FAILURE;
        }

        $old = $project->public_key;
        $new = $rotate->handle($project);

        if ($this->option('json')) {
            return $this->printJson(['id' => $project->id, 'old_public_key' => $old, 'public_key' => $new, 'snippet' => $this->snippet($project)]);
        }

        $this->components->info('Schlüssel erneuert. Der alte Schlüssel ist ab sofort ungültig.');
        $this->components->twoColumnDetail('Public Key', $new);
        $this->line('Init-Snippet:');
        $this->line('  '.$this->snippet($project));

        return self::SUCCESS;
    }
}
