<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Console\Commands\Feedback\Concerns\InteractsWithFeedback;
use Illuminate\Console\Command;

final class ExportFeedbackCommand extends Command
{
    use InteractsWithFeedback;

    protected $signature = 'feedback:export {id : Feedback ID} {--path= : Target directory (default: current directory)}';

    protected $description = 'Write feedback-{id}.json and the screenshot to a directory';

    public function handle(): int
    {
        $entry = $this->findEntry($this->stringArgument('id'));

        if ($entry === null) {
            return self::FAILURE;
        }

        $dir = rtrim($this->stringOption('path') ?? (string) getcwd(), DIRECTORY_SEPARATOR);

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->components->error("Directory [{$dir}] cannot be created.");

            return self::FAILURE;
        }

        $json = $dir.DIRECTORY_SEPARATOR."feedback-{$entry->id}.json";
        file_put_contents($json, json_encode(ShowFeedbackCommand::entryData($entry), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->components->twoColumnDetail('JSON', $json);

        $shot = $entry->screenshot;

        if ($shot !== null && $shot->data !== null) {
            $image = $dir.DIRECTORY_SEPARATOR.'screenshot.'.$shot->extension();
            file_put_contents($image, $shot->data);
            $this->components->twoColumnDetail('Screenshot', $image);
        }

        $this->components->info('Export finished.');

        return self::SUCCESS;
    }
}
