<?php

declare(strict_types=1);

use App\Models\FeedbackEntry;
use App\Models\FeedbackProject;

beforeEach(function (): void {
    $this->project = FeedbackProject::factory()->create(['allowed_origins' => [TEST_ORIGIN]]);
});

it('has no limit when switched off with 0', function (): void {
    foreach (range(1, 15) as $i) {
        postFeedback($this->project)->assertCreated();
    }
});

it('limits per IP and project', function (): void {
    config(['feedback.limits.per_ip_per_minute' => 2]);

    postFeedback($this->project, ip: '203.0.113.1')->assertCreated();
    postFeedback($this->project, ip: '203.0.113.1')->assertCreated();
    $response = postFeedback($this->project, ip: '203.0.113.1');

    $response->assertTooManyRequests()
        ->assertExactJson(['error' => ['code' => 'rate_limited']])
        ->assertHeader('Access-Control-Allow-Origin', TEST_ORIGIN);
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);

    postFeedback($this->project, ip: '203.0.113.2')->assertCreated();
});

it('limits per project', function (): void {
    config(['feedback.limits.per_project_per_minute' => 1]);

    postFeedback($this->project, ip: '203.0.113.1')->assertCreated();
    postFeedback($this->project, ip: '203.0.113.2')->assertTooManyRequests();
});

it('applies the daily limit of the project', function (): void {
    $this->project->update(['daily_limit' => 2]);

    postFeedback($this->project)->assertCreated();
    postFeedback($this->project)->assertCreated();
    postFeedback($this->project)->assertTooManyRequests();

    $this->travel(1)->days();
    postFeedback($this->project)->assertCreated();

    expect(FeedbackEntry::query()->count())->toBe(3);
});
