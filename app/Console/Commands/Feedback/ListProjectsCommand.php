<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Console\Commands\Feedback\Concerns\InteractsWithFeedback;
use App\Models\FeedbackProject;
use Illuminate\Console\Command;

final class ListProjectsCommand extends Command
{
    use InteractsWithFeedback;

    protected $signature = 'feedback:project:list {--json : Output as JSON}';

    protected $description = 'List all feedback projects';

    public function handle(): int
    {
        $projects = FeedbackProject::query()
            ->withCount([
                'entries as open_count' => fn ($q) => $q->whereNull('completed_at'),
                'entries as completed_count' => fn ($q) => $q->whereNotNull('completed_at'),
            ])
            ->orderBy('name')
            ->get();

        if ($this->option('json')) {
            return $this->printJson($projects->map(fn (FeedbackProject $p): array => $this->projectData($p))->all());
        }

        if ($projects->isEmpty()) {
            $this->components->info('No projects yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Key', 'Active', 'Color', 'Origins', 'IPs', 'Open', 'Completed'],
            $projects->map(fn (FeedbackProject $p): array => [
                $p->name,
                $p->public_key,
                $p->is_active ? 'yes' : 'no',
                $p->color()->primary,
                implode(', ', $p->allowed_origins),
                $p->allowed_ips === [] ? '–' : implode(', ', $p->allowed_ips),
                $this->countAttribute($p, 'open_count'),
                $this->countAttribute($p, 'completed_count'),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
