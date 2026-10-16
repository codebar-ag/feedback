<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Console\Commands\Feedback\Concerns\InteractsWithFeedback;
use App\Models\FeedbackEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ListFeedbackCommand extends Command
{
    use InteractsWithFeedback;

    protected $signature = 'feedback:list
        {--project= : Public key or ID of the project}
        {--open : Only open entries}
        {--completed : Only completed entries}
        {--limit=20 : Number of entries}
        {--json : Output as JSON}';

    protected $description = 'List the newest feedback entries';

    public function handle(): int
    {
        $limit = $this->stringOption('limit') ?? '20';

        if (! ctype_digit($limit) || (int) $limit < 1) {
            $this->components->error('--limit must be a positive integer.');

            return self::INVALID;
        }

        $query = FeedbackEntry::query()->with('project:id,name,public_key')->latest()->latest('id')->limit((int) $limit);

        $projectKey = $this->stringOption('project');

        if ($projectKey !== null) {
            $project = $this->findProject($projectKey);

            if ($project === null) {
                return self::FAILURE;
            }

            $query->where('project_id', $project->id);
        }

        if ($this->option('open')) {
            $query->open();
        }

        if ($this->option('completed')) {
            $query->completed();
        }

        $entries = $query->get();

        if ($this->option('json')) {
            return $this->printJson($entries->map(fn (FeedbackEntry $e): array => [
                'id' => $e->id,
                'project' => $e->project->public_key,
                'created_at' => $e->created_at->toIso8601String(),
                'completed_at' => $e->completed_at?->toIso8601String(),
                'email' => $e->email,
                'message' => $e->message,
                'page_url' => $e->page_url,
            ])->all());
        }

        if ($entries->isEmpty()) {
            $this->components->info('Keine Feedbacks gefunden.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Projekt', 'Erstellt', 'Status', 'E-Mail', 'Feedback'],
            $entries->map(fn (FeedbackEntry $e): array => [
                $e->id,
                $e->project->name,
                $e->created_at->format('Y-m-d H:i'),
                $e->completed_at === null ? 'offen' : 'erledigt',
                $e->email,
                Str::limit(str_replace(["\r", "\n"], ' ', $e->message), 60),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
