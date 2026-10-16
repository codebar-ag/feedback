<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Console\Commands\Feedback\Concerns\InteractsWithFeedback;
use App\Models\FeedbackEntry;
use Illuminate\Console\Command;

final class ShowFeedbackCommand extends Command
{
    use InteractsWithFeedback;

    protected $signature = 'feedback:show {id : Feedback ID} {--json : Output as JSON}';

    protected $description = 'Show a feedback entry with metadata and console';

    public function handle(): int
    {
        $entry = $this->findEntry($this->stringArgument('id'));

        if ($entry === null) {
            return self::FAILURE;
        }

        $data = self::entryData($entry);

        if ($this->option('json')) {
            return $this->printJson($data);
        }

        $this->components->twoColumnDetail('ID', $entry->id);
        $this->components->twoColumnDetail('Projekt', $entry->project->name);
        $this->components->twoColumnDetail('Erstellt', $entry->created_at->toDateTimeString());
        $this->components->twoColumnDetail('Status', $entry->completed_at === null ? 'offen' : 'erledigt ('.$entry->completed_at->toDateTimeString().')');
        $this->components->twoColumnDetail('Von', trim(($entry->name ?? '').' <'.$entry->email.'>'));
        $this->components->twoColumnDetail('Sprache', $entry->locale);
        $this->components->twoColumnDetail('Seite', $entry->page_url);

        if ($entry->submitted_from_url !== null && $entry->submitted_from_url !== $entry->page_url) {
            $this->components->twoColumnDetail('Gesendet von', $entry->submitted_from_url);
        }

        $this->components->twoColumnDetail('System', trim(($entry->os_name ?? '?').' '.($entry->os_version ?? '')));
        $this->components->twoColumnDetail('Browser', trim(($entry->browser_name ?? '?').' '.($entry->browser_version ?? '')).' ('.$entry->version_precision.')');
        $this->components->twoColumnDetail('Browserfenster', $entry->viewport_w.' × '.$entry->viewport_h);
        $this->components->twoColumnDetail('Auflösung', $entry->screen_w.' × '.$entry->screen_h.' @'.$entry->dpr);
        $this->components->twoColumnDetail('IP', $entry->ip ?? '–');
        $shot = self::screenshotMeta($entry);
        $this->components->twoColumnDetail('Screenshot', $shot === null ? ($entry->screenshot_error ?? 'keiner') : $shot['width'].' × '.$shot['height'].', '.$shot['bytes'].' Bytes');
        $this->components->twoColumnDetail('Konsole', count($entry->console_log ?? []).' Einträge');
        $this->newLine();
        $this->line($entry->message);

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    public static function entryData(FeedbackEntry $entry): array
    {
        return [
            ...collect($entry->attributesToArray())->except(['project_id'])->all(),
            'project' => ['id' => $entry->project->id, 'name' => $entry->project->name, 'public_key' => $entry->project->public_key],
            'created_at' => $entry->created_at->toIso8601String(),
            'updated_at' => $entry->updated_at->toIso8601String(),
            'completed_at' => $entry->completed_at?->toIso8601String(),
            'screenshot' => self::screenshotMeta($entry),
        ];
    }

    /** @return array{mime: string, width: int, height: int, bytes: int, sha256: string}|null */
    private static function screenshotMeta(FeedbackEntry $entry): ?array
    {
        // Metadata only, the BLOB itself is never loaded here.
        $shot = $entry->screenshot()->select(['entry_id', 'mime', 'width', 'height', 'bytes', 'sha256'])->first();

        return $shot === null ? null : [
            'mime' => $shot->mime,
            'width' => $shot->width,
            'height' => $shot->height,
            'bytes' => $shot->bytes,
            'sha256' => $shot->sha256,
        ];
    }
}
