<?php

declare(strict_types=1);

use App\Events\FeedbackReceived;
use App\Geo\GeoLocator;
use App\Models\FeedbackEntry;
use App\Models\FeedbackProject;
use App\Models\FeedbackScreenshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->project = FeedbackProject::factory()->create(['allowed_origins' => [TEST_ORIGIN, 'http://localhost:*']]);
});

it('stores feedback with the required fields', function (): void {
    Event::fake([FeedbackReceived::class]);
    $key = (string) Str::uuid();

    $response = postFeedback($this->project, headers: ['Idempotency-Key' => $key]);

    $entry = FeedbackEntry::query()->sole();
    $response->assertCreated()
        ->assertExactJson(['id' => $entry->id, 'status' => 'received'])
        ->assertHeader('Access-Control-Allow-Origin', TEST_ORIGIN);

    expect($entry)
        ->project_id->toBe($this->project->id)
        ->client_request_id->toBe($key)
        ->source->toBe('widget')
        ->email->toBe('anna@beispiel.ch')
        ->name->toBeNull()
        ->locale->toBe('de_CH')
        ->origin->toBe(TEST_ORIGIN)
        ->ip->toBe('127.0.0.1')
        ->viewport_w->toBe(1541)
        ->page_h->toBe(6120)
        ->dpr->toBe('2.00')
        ->color_depth->toBe(30)
        ->timezone->toBe('Europe/Zurich')
        ->browser_language->toBe('de-CH')
        ->widget_version->toBe('1.0.0')
        ->elapsed_ms->toBe(12000)
        ->completed_at->toBeNull()
        ->and($entry->screenshot)->toBeNull();

    Event::assertDispatched(FeedbackReceived::class, fn (FeedbackReceived $e): bool => $e->entry->is($entry));
});

it('stores all optional fields and the screenshot', function (): void {
    $shot = fakeJpeg(320, 640);
    $bytes = (string) file_get_contents($shot->getRealPath());

    postFeedback($this->project, [
        'identity' => json_encode(['id' => '4711', 'name' => 'Anna Muster', 'email' => 'anna.muster@beispiel.ch']),
        'email' => '',
        'page_title' => 'Konto',
        'referrer' => 'https://www.google.ch/',
        'console_log' => json_encode([['level' => 'error', 'message' => 'TypeError: x is undefined', 'count' => 2]]),
        'network_log' => json_encode([['method' => 'GET', 'url' => 'https://api.beispiel.ch/items', 'status' => 500, 'duration' => 312]]),
        'annotations' => json_encode([['type' => 'rect', 'x' => 60, 'y' => 112, 'w' => 300, 'h' => 150], ['type' => 'marker', 'points' => [[1, 2], [3, 4], [5, 6]]]]),
        'custom_data' => json_encode(['app_version' => '2.3.1', 'tenant' => 'acme']),
        'submitted_from_url' => 'https://www.beispiel.ch/andere-seite',
        'screenshot' => $shot,
    ], ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0'])->assertCreated();

    $entry = FeedbackEntry::query()->sole();

    expect($entry)
        ->name->toBe('Anna Muster')
        ->email->toBe('anna.muster@beispiel.ch')
        ->identity_user_id->toBe('4711')
        ->page_title->toBe('Konto')
        ->referrer->toBe('https://www.google.ch/')
        ->submitted_from_url->toBe('https://www.beispiel.ch/andere-seite')
        ->browser_name->toBe('Firefox')
        ->os_name->toBe('Windows')
        ->version_precision->toBe('approximate')
        ->user_agent->toContain('Firefox/140.0')
        ->console_log->toEqual([['level' => 'error', 'message' => 'TypeError: x is undefined', 'count' => 2]])
        ->network_log->toHaveCount(1)
        ->annotations->toHaveCount(2)
        ->custom_data->toEqual(['app_version' => '2.3.1', 'tenant' => 'acme'])
        ->screenshot_error->toBeNull();

    $screenshot = FeedbackScreenshot::query()->findOrFail($entry->id);

    expect($screenshot)
        ->mime->toBe('image/jpeg')
        ->width->toBe(320)
        ->height->toBe(640)
        ->bytes->toBe(strlen($bytes))
        ->sha256->toBe(hash('sha256', $bytes))
        ->data->toBe($bytes);
});

it('keeps the screenshot error when no screenshot was sent', function (): void {
    postFeedback($this->project, ['screenshot_error' => 'render_failed'])->assertCreated();

    expect(FeedbackEntry::query()->sole()->screenshot_error)->toBe('render_failed');
});

it('answers a repeated Idempotency-Key with 200 and no new entry', function (): void {
    Event::fake([FeedbackReceived::class]);
    $key = (string) Str::uuid();

    $first = postFeedback($this->project, headers: ['Idempotency-Key' => $key])->assertCreated();
    $second = postFeedback($this->project, ['message' => 'Anderer Text'], ['Idempotency-Key' => $key])->assertOk();

    expect($second->json('id'))->toBe($first->json('id'))
        ->and(FeedbackEntry::query()->count())->toBe(1);
    Event::assertDispatchedTimes(FeedbackReceived::class, 1);
});

it('scopes idempotency keys per project', function (): void {
    $other = FeedbackProject::factory()->create(['allowed_origins' => [TEST_ORIGIN]]);
    $key = (string) Str::uuid();

    postFeedback($this->project, headers: ['Idempotency-Key' => $key])->assertCreated();
    postFeedback($other, headers: ['Idempotency-Key' => $key])->assertCreated();

    expect(FeedbackEntry::query()->count())->toBe(2);
});

it('rejects a foreign origin with 403', function (?string $origin): void {
    $headers = $origin === null ? [] : ['Origin' => $origin];

    $response = test()->post("/v1/projects/{$this->project->public_key}/feedback", feedbackPayload(), [...$headers, 'Idempotency-Key' => (string) Str::uuid()]);

    $response->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'origin_not_allowed']])
        ->assertHeaderMissing('Access-Control-Allow-Origin');
    expect(FeedbackEntry::query()->count())->toBe(0);
})->with([
    'other site' => ['https://evil.example'],
    'suffix attack' => ['https://www.beispiel.ch.evil.com'],
    'missing origin' => [null],
]);

it('rejects IPs outside the allowed ranges with 403', function (): void {
    $this->project->update(['allowed_ips' => ['10.0.0.0/8', '2001:db8::/32']]);

    postFeedback($this->project, ip: '198.51.100.20')
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'origin_not_allowed']])
        ->assertHeader('Access-Control-Allow-Origin', TEST_ORIGIN);

    postFeedback($this->project, ip: '10.20.30.40')->assertCreated();
    postFeedback($this->project, ip: '2001:db8::7')->assertCreated();
});

it('answers unknown and inactive projects with the same 404', function (string $key): void {
    FeedbackProject::factory()->inactive()->create(['public_key' => 'fbk_live_inactiveAAAAAAAAAAAAAA']);

    test()->post("/v1/projects/{$key}/feedback", feedbackPayload(), ['Origin' => TEST_ORIGIN])
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'project_not_found']]);
})->with([
    'unknown' => ['fbk_live_unknownAAAAAAAAAAAAAAA'],
    'inactive' => ['fbk_live_inactiveAAAAAAAAAAAAAA'],
    'malformed' => ['abc'],
]);

it('rejects screenshots above 6 MB with 413', function (): void {
    postFeedback($this->project, ['screenshot' => UploadedFile::fake()->create('big.jpg', 6 * 1024 + 1, 'image/jpeg')])
        ->assertStatus(413)
        ->assertExactJson(['error' => ['code' => 'payload_too_large']])
        ->assertHeader('Access-Control-Allow-Origin', TEST_ORIGIN);
});

it('rejects requests above post_max_size with 413', function (): void {
    $response = test()->call('POST', "/v1/projects/{$this->project->public_key}/feedback", [], [], [], [
        'HTTP_ORIGIN' => TEST_ORIGIN,
        'CONTENT_LENGTH' => (string) (PHP_INT_MAX),
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $response->assertStatus(413)->assertExactJson(['error' => ['code' => 'payload_too_large']]);
});

it('rejects invalid images with 422', function (): void {
    postFeedback($this->project, ['screenshot' => UploadedFile::fake()->createWithContent('shot.jpg', '<html>nope</html>')])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => ['screenshot']]]);
});

it('validates every rule', function (array $payload, string $field, array $headers = []): void {
    postFeedback($this->project, $payload, $headers)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => [$field]]]);

    expect(FeedbackEntry::query()->count())->toBe(0);
})->with([
    'message missing' => [['message' => ''], 'message'],
    'message too short' => [['message' => ' ab '], 'message'],
    'message too long' => [['message' => str_repeat('a', 5001)], 'message'],
    'email missing' => [['email' => ''], 'email'],
    'email invalid' => [['email' => 'anna@'], 'email'],
    'email too long' => [['email' => str_repeat('a', 250).'@b.ch'], 'email'],
    'name without identity' => [['name' => 'Anna'], 'name'],
    'locale missing' => [['locale' => ''], 'locale'],
    'locale unknown' => [['locale' => 'de_DE'], 'locale'],
    'page_url missing' => [['page_url' => ''], 'page_url'],
    'page_url invalid' => [['page_url' => 'javascript:alert(1)'], 'page_url'],
    'page_url too long' => [['page_url' => 'https://a.ch/'.str_repeat('a', 2040)], 'page_url'],
    'page_title too long' => [['page_title' => str_repeat('a', 301)], 'page_title'],
    'referrer too long' => [['referrer' => str_repeat('a', 2049)], 'referrer'],
    'client missing' => [['client' => ''], 'client'],
    'client not json' => [['client' => 'x'], 'client'],
    'client list' => [['client' => '[1,2]'], 'client'],
    'client too large' => [['client' => json_encode(['x' => str_repeat('a', 4100)])], 'client'],
    'console_log object' => [['console_log' => '{"a":1}'], 'console_log'],
    'console_log too large' => [['console_log' => json_encode([str_repeat('a', 33000)])], 'console_log'],
    'network_log too large' => [['network_log' => json_encode([str_repeat('a', 17000)])], 'network_log'],
    'annotations invalid type' => [['annotations' => json_encode([['type' => 'arrow']])], 'annotations'],
    'annotations rect without size' => [['annotations' => json_encode([['type' => 'rect', 'x' => 1]])], 'annotations'],
    'annotations too large' => [['annotations' => json_encode(array_fill(0, 900, ['type' => 'rect', 'x' => 1, 'y' => 1, 'w' => 1, 'h' => 1]))], 'annotations'],
    'custom_data nested' => [['custom_data' => json_encode(['a' => ['b' => 1]])], 'custom_data'],
    'custom_data too large' => [['custom_data' => json_encode(['a' => str_repeat('x', 8200)])], 'custom_data'],
    'identity not json' => [['identity' => 'x'], 'identity'],
    'identity name too long' => [['identity' => json_encode(['name' => str_repeat('a', 121), 'email' => 'a@b.ch'])], 'identity'],
    'identity email invalid' => [['identity' => json_encode(['name' => 'Anna', 'email' => 'nope'])], 'email'],
    'submitted_from_url invalid' => [['submitted_from_url' => 'nope'], 'submitted_from_url'],
    'screenshot_error invalid' => [['screenshot_error' => 'Render Failed!'], 'screenshot_error'],
    'widget_version missing' => [['widget_version' => ''], 'widget_version'],
    'widget_version too long' => [['widget_version' => str_repeat('1', 21)], 'widget_version'],
    'elapsed_ms missing' => [['elapsed_ms' => ''], 'elapsed_ms'],
    'elapsed_ms negative' => [['elapsed_ms' => -1], 'elapsed_ms'],
    'screenshot not a file' => [['screenshot' => 'abc'], 'screenshot'],
    'idempotency key missing' => [[], 'idempotency_key', ['Idempotency-Key' => '']],
    'idempotency key invalid' => [[], 'idempotency_key', ['Idempotency-Key' => 'abc']],
]);

it('accepts the honeypot silently with 201 without storing', function (): void {
    postFeedback($this->project, ['website' => 'https://spam.example'])
        ->assertCreated()
        ->assertJsonPath('status', 'received');

    expect(FeedbackEntry::query()->count())->toBe(0);
});

it('accepts too fast submissions silently when a minimum time is configured', function (): void {
    config(['feedback.limits.min_elapsed_ms' => 2000]);

    postFeedback($this->project, ['elapsed_ms' => 300])->assertCreated();
    expect(FeedbackEntry::query()->count())->toBe(0);

    postFeedback($this->project, ['elapsed_ms' => 2500])->assertCreated();
    expect(FeedbackEntry::query()->count())->toBe(1);
});

it('removes control characters but keeps line breaks', function (): void {
    postFeedback($this->project, ['message' => "Zeile 1\nZeile\x00 2\x07", 'page_title' => "Titel\x1b[31m"])->assertCreated();

    expect(FeedbackEntry::query()->sole())
        ->message->toBe("Zeile 1\nZeile 2")
        ->page_title->toBe('Titel[31m');
});

it('anonymizes the IP when configured', function (string $ip, string $stored): void {
    config(['feedback.ip_mode' => 'anonymized']);

    postFeedback($this->project, ip: $ip)->assertCreated();

    expect(FeedbackEntry::query()->sole()->ip)->toBe($stored);
})->with([
    ['203.0.113.77', '203.0.113.0'],
    ['2001:db8:abcd:1234::1', '2001:db8:abcd::'],
]);

it('stores the location from Cloudflare headers', function (): void {
    config(['feedback.geo' => 'cloudflare']);
    app()->forgetInstance(GeoLocator::class);

    postFeedback($this->project, headers: ['CF-IPCountry' => 'CH', 'CF-IPCity' => 'Meilen', 'CF-Postal-Code' => '8706'])->assertCreated();

    expect(FeedbackEntry::query()->sole())
        ->geo_country->toBe('CH')
        ->geo_city->toBe('Meilen')
        ->geo_postcode->toBe('8706');
});

it('ignores Cloudflare headers when geo is off', function (): void {
    postFeedback($this->project, headers: ['CF-IPCountry' => 'CH'])->assertCreated();

    expect(FeedbackEntry::query()->sole()->geo_country)->toBeNull();
});

it('never leaks internals on server errors', function (): void {
    FeedbackEntry::creating(fn () => throw new RuntimeException('SQLSTATE secret details'));

    postFeedback($this->project)
        ->assertStatus(500)
        ->assertExactJson(['error' => ['code' => 'server_error']]);
});
