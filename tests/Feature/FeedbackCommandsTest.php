<?php

declare(strict_types=1);

use App\Models\FeedbackEntry;
use App\Models\FeedbackProject;
use App\Models\FeedbackScreenshot;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->project = FeedbackProject::factory()->create(['name' => 'Alpha', 'allowed_origins' => [TEST_ORIGIN]]);
});

it('lists the newest feedback, filtered', function (): void {
    $other = FeedbackProject::factory()->create();
    $open = FeedbackEntry::factory()->for($this->project, 'project')->create(['message' => "Zeile 1\nZeile 2", 'created_at' => now()->subMinute()]);
    $done = FeedbackEntry::factory()->for($this->project, 'project')->completed()->create();
    FeedbackEntry::factory()->for($other, 'project')->create();

    test()->artisan('feedback:list', ['--project' => $this->project->public_key])
        ->expectsOutputToContain('Zeile 1 Zeile 2')
        ->expectsOutputToContain('completed')
        ->assertExitCode(0);

    Artisan::call('feedback:list', ['--json' => true, '--project' => $this->project->public_key, '--open' => true]);
    $data = json_decode(Artisan::output(), true);
    expect(array_column($data, 'id'))->toBe([$open->id]);

    Artisan::call('feedback:list', ['--json' => true, '--completed' => true]);
    expect(array_column(json_decode(Artisan::output(), true), 'id'))->toBe([$done->id]);

    Artisan::call('feedback:list', ['--json' => true, '--limit' => '1']);
    expect(json_decode(Artisan::output(), true))->toHaveCount(1);

    Artisan::call('feedback:list', ['--json' => true]);
    expect(json_decode(Artisan::output(), true))->toHaveCount(3);
});

it('validates list options', function (): void {
    test()->artisan('feedback:list', ['--limit' => 'x'])->assertExitCode(2);
    test()->artisan('feedback:list', ['--project' => 'unknown'])->assertExitCode(1);
    test()->artisan('feedback:list')->expectsOutputToContain('No feedback found')->assertExitCode(0);
});

it('shows an entry with metadata and console', function (): void {
    postFeedback($this->project, [
        'screenshot' => fakeJpeg(200, 400),
        'console_log' => json_encode([['level' => 'error', 'message' => 'Boom']]),
        'submitted_from_url' => 'https://www.beispiel.ch/zwei',
    ])->assertCreated();
    $entry = FeedbackEntry::query()->sole();

    test()->artisan('feedback:show', ['id' => $entry->id])
        ->expectsOutputToContain('Alpha')
        ->expectsOutputToContain('open')
        ->expectsOutputToContain('Die Schaltfläche')
        ->expectsOutputToContain('200 × 400')
        ->expectsOutputToContain('1 entries')
        ->expectsOutputToContain('Sent from')
        ->assertExitCode(0);

    Artisan::call('feedback:show', ['id' => $entry->id, '--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($data)
        ->toMatchArray(['id' => $entry->id, 'completed_at' => null, 'email' => 'anna@beispiel.ch'])
        ->and($data['project']['public_key'])->toBe($this->project->public_key)
        ->and($data['screenshot'])->toMatchArray(['mime' => 'image/jpeg', 'width' => 200, 'height' => 400])
        ->and($data['console_log'][0]['message'])->toBe('Boom')
        ->and($data)->not->toHaveKey('data');

    test()->artisan('feedback:show', ['id' => '01JUNKNOWN'])->assertExitCode(1);
});

it('shows an entry without screenshot', function (): void {
    $entry = FeedbackEntry::factory()->for($this->project, 'project')->completed()->create(['screenshot_error' => 'render_failed']);

    test()->artisan('feedback:show', ['id' => $entry->id])
        ->expectsOutputToContain('render_failed')
        ->expectsOutputToContain('completed')
        ->assertExitCode(0);
});

it('exports json and screenshot', function (): void {
    postFeedback($this->project, ['screenshot' => fakeJpeg()])->assertCreated();
    $entry = FeedbackEntry::query()->sole();
    $dir = sys_get_temp_dir().'/feedback-export-'.uniqid();

    test()->artisan('feedback:export', ['id' => $entry->id, '--path' => $dir])->expectsOutputToContain('Export finished')->assertExitCode(0);

    $json = json_decode((string) file_get_contents("{$dir}/feedback-{$entry->id}.json"), true);
    $image = (string) file_get_contents("{$dir}/screenshot.jpg");

    expect($json['id'])->toBe($entry->id)
        ->and(hash('sha256', $image))->toBe(FeedbackScreenshot::query()->sole()->sha256);

    test()->artisan('feedback:export', ['id' => 'nope'])->assertExitCode(1);
    test()->artisan('feedback:export', ['id' => $entry->id, '--path' => '/dev/null/x'])->assertExitCode(1);
});

it('exports into the working directory without screenshot', function (): void {
    $entry = FeedbackEntry::factory()->for($this->project, 'project')->create();
    $dir = sys_get_temp_dir().'/feedback-cwd-'.uniqid();
    mkdir($dir);
    $cwd = getcwd();
    chdir($dir);

    try {
        test()->artisan('feedback:export', ['id' => $entry->id])->assertExitCode(0);
    } finally {
        chdir((string) $cwd);
    }

    expect(file_exists("{$dir}/feedback-{$entry->id}.json"))->toBeTrue()
        ->and(glob("{$dir}/screenshot.*"))->toBe([]);
});

it('completes and reopens entries', function (): void {
    [$a, $b] = FeedbackEntry::factory()->for($this->project, 'project')->count(2)->create();

    test()->artisan('feedback:complete', ['id' => [$a->id, $b->id]])->assertExitCode(0);
    expect($a->refresh()->completed_at)->not->toBeNull()->and($b->refresh()->completed_at)->not->toBeNull();

    test()->artisan('feedback:complete', ['id' => [$a->id, 'nope']])->assertExitCode(1);

    test()->artisan('feedback:reopen', ['id' => [$a->id]])->assertExitCode(0);
    expect($a->refresh()->completed_at)->toBeNull();

    test()->artisan('feedback:reopen', ['id' => [$a->id, 'nope']])->assertExitCode(1);
});
