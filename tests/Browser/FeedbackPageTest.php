<?php

declare(strict_types=1);

use App\Models\FeedbackEntry;
use App\Models\FeedbackProject;
use App\Support\FeedbackLink;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->project = FeedbackProject::factory()->create(['name' => 'Alpha', 'allowed_origins' => [TEST_ORIGIN]]);
});

/** Sends feedback the way the widget does, with screenshot and diagnostics. */
function sentFeedback(FeedbackProject $project): FeedbackEntry
{
    postFeedback($project, [
        'screenshot' => fakeJpeg(200, 400),
        'identity' => json_encode(['id' => 'u-1', 'name' => 'Anna Muster']),
        'console_log' => json_encode([['type' => 'error', 'message' => 'Boom', 'count' => 2, 'time' => 1]]),
        'network_log' => json_encode([['method' => 'POST', 'url' => 'https://api.beispiel.ch/save', 'status' => 500, 'duration' => 120, 'count' => 1, 'time' => 1]]),
    ])->assertCreated();

    return FeedbackEntry::query()->latest('id')->firstOrFail();
}

it('shows the message, the sender and the screenshot', function (): void {
    $entry = sentFeedback($this->project);

    $page = visit(FeedbackLink::page($entry, now()->addHour()));

    $page->assertSee('Alpha')
        ->assertSee($entry->id)
        ->assertSee('open')
        ->assertSee('Die Schaltfläche «Speichern» reagiert nicht.')
        ->assertSee('Anna Muster')
        ->assertSee('anna@beispiel.ch')
        ->assertSee('1 console error')
        ->assertVisible('img[alt^="Screenshot of"]')
        ->assertNoJavaScriptErrors();

    expect($page->script('() => document.querySelector(\'img[alt^="Screenshot of"]\').naturalWidth'))->toBe(200);
});

it('switches to the technical tab with console errors, failed requests and the environment', function (): void {
    $entry = sentFeedback($this->project);

    $page = visit(FeedbackLink::page($entry, now()->addHour()));

    $page->assertDontSee('Failed requests')
        ->click('#tab-technical')
        ->assertSee('Failed requests')
        ->assertSee('Boom')
        ->assertSee('https://api.beispiel.ch/save')
        ->assertSee('Environment')
        ->assertSee('Europe/Zurich')
        ->assertDontSee('Die Schaltfläche «Speichern» reagiert nicht.')
        ->assertAttribute('#tab-technical', 'aria-selected', 'true')
        ->assertNoJavaScriptErrors();

    expect($page->script('() => location.hash'))->toBe('#technical');

    $page->click('#tab-feedback')
        ->assertSee('Die Schaltfläche «Speichern» reagiert nicht.')
        ->assertDontSee('Failed requests');
});

it('opens the technical tab directly from the #technical link', function (): void {
    $entry = sentFeedback($this->project);

    visit(FeedbackLink::page($entry, now()->addHour()).'#technical')
        ->assertSee('Failed requests')
        ->assertAttribute('#tab-technical', 'aria-selected', 'true')
        ->assertNoJavaScriptErrors();
});

it('hides and shows the message card', function (): void {
    $entry = sentFeedback($this->project);

    visit(FeedbackLink::page($entry, now()->addHour()))
        ->click('#card-toggle')
        ->assertAttribute('#card-toggle', 'aria-expanded', 'false')
        ->assertAttribute('#card-toggle', 'aria-label', 'Show message')
        ->click('#card-toggle')
        ->assertAttribute('#card-toggle', 'aria-expanded', 'true')
        ->assertNoJavaScriptErrors();
});

it('copies the feedback ID and announces it', function (): void {
    $entry = sentFeedback($this->project);

    visit(FeedbackLink::page($entry, now()->addHour()))
        ->click('[aria-label="Copy feedback ID"]')
        ->assertSeeIn('#live', 'Copied to clipboard')
        ->assertNoJavaScriptErrors();
});

it('lets the team complete and reopen an entry from a team link', function (): void {
    $entry = sentFeedback($this->project);

    $page = visit(FeedbackLink::page($entry, now()->addHour(), manage: true, by: 'Sebastian'));

    $page->press('Mark as completed')
        ->assertSee('completed')
        ->assertSee('Reopen')
        ->assertDontSee('Mark as completed')
        ->assertNoJavaScriptErrors();

    expect($entry->fresh()->completed_at)->not->toBeNull()
        ->and(Activity::query()->where('subject_id', $entry->id)->sole()->properties['by'])->toBe('Sebastian');

    $page->press('Reopen')
        ->assertSee('Mark as completed')
        ->assertNoJavaScriptErrors();

    expect($entry->fresh()->completed_at)->toBeNull();
});

it('shows no status buttons on the read-only link the sender gets', function (): void {
    $entry = sentFeedback($this->project);

    visit(FeedbackLink::page($entry, now()->addHour()))
        ->assertSee('open')
        ->assertDontSee('Mark as completed')
        ->assertNoJavaScriptErrors();
});

it('rejects expired and tampered links with an empty page', function (string $url): void {
    $entry = sentFeedback($this->project);
    $url = str_replace('{entry}', $entry->id, $url);

    $page = visit($url === 'expired' ? FeedbackLink::page($entry, now()->subMinute()) : $url);

    expect($page->script('() => document.body.innerText.trim()'))->toBe('');
    $page->assertDontSee('Alpha');
})->with([
    'expired' => ['expired'],
    'tampered' => ['/v1/feedback/{entry}?expires=4102444800&signature=forged'],
]);

it('answers a typed API URL with an empty page', function (): void {
    $page = visit("/v1/projects/{$this->project->public_key}/config");

    expect($page->script('() => document.body.innerText.trim()'))->toBe('');
});
