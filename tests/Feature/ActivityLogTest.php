<?php

declare(strict_types=1);

use App\Models\FeedbackEntry;
use App\Models\FeedbackProject;
use Spatie\Activitylog\Models\Activity;

it('logs project changes with via artisan and by', function (): void {
    test()->artisan('feedback:project:create', ['name' => 'Alpha', '--origin' => ['https://a.ch'], '--by' => 'Sebastian'])->assertExitCode(0);
    $project = FeedbackProject::query()->sole();

    test()->artisan('feedback:project:update', ['key' => $project->id, '--add-ip' => ['10.0.0.0/8'], '--color' => 'white', '--limit' => '10', '--deactivate' => true])->assertExitCode(0);
    test()->artisan('feedback:project:rotate-key', ['key' => $project->id, '--by' => 'Anna'])->assertExitCode(0);

    $log = Activity::query()->where('log_name', 'feedback')->orderBy('id')->get();

    expect($log->pluck('event')->all())->toBe(['created', 'updated', 'updated'])
        ->and($log[0]->properties->all())->toEqual(['via' => 'artisan', 'by' => 'Sebastian'])
        ->and($log[1]->properties->all())->toBe(['via' => 'artisan'])
        ->and(array_keys($log[1]->attribute_changes['attributes']))->toEqualCanonicalizing(['theme', 'allowed_ips', 'is_active', 'daily_limit'])
        ->and(array_keys($log[2]->attribute_changes['attributes']))->toBe(['public_key'])
        ->and($log[2]->properties['by'])->toBe('Anna')
        ->and($log[2]->subject_id)->toBe($project->id);
});

it('does not log incoming feedback', function (): void {
    $project = FeedbackProject::factory()->create(['allowed_origins' => [TEST_ORIGIN]]);
    Activity::query()->delete();

    postFeedback($project)->assertCreated();

    expect(Activity::query()->count())->toBe(0);
});

it('logs completed and reopened without personal data', function (): void {
    $entry = FeedbackEntry::factory()->create(['email' => 'secret@beispiel.ch', 'name' => 'Geheim', 'message' => 'Sehr privat', 'ip' => '203.0.113.9']);
    Activity::query()->delete();

    test()->artisan('feedback:complete', ['id' => [$entry->id], '--by' => 'Anna'])->assertExitCode(0);
    test()->artisan('feedback:reopen', ['id' => [$entry->id]])->assertExitCode(0);

    // A change to personal fields alone is not logged.
    $entry->refresh()->update(['message' => 'geändert']);

    $log = Activity::query()->orderBy('id')->get();
    $serialized = $log->toJson();

    expect($log->pluck('description')->all())->toBe(['completed', 'reopened'])
        ->and($log[0]->properties->all())->toEqual(['via' => 'artisan', 'by' => 'Anna'])
        ->and($serialized)->not->toContain('secret@beispiel.ch')
        ->and($serialized)->not->toContain('Geheim')
        ->and($serialized)->not->toContain('Sehr privat')
        ->and($serialized)->not->toContain('203.0.113.9');
});
