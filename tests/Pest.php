<?php

declare(strict_types=1);

use App\Models\FeedbackProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Browser');

const TEST_ORIGIN = 'https://www.beispiel.ch';

/**
 * Valid multipart payload as the widget sends it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function feedbackPayload(array $overrides = []): array
{
    return array_merge([
        'message' => 'Die Schaltfläche «Speichern» reagiert nicht.',
        'email' => 'anna@beispiel.ch',
        'locale' => 'de_CH',
        'page_url' => 'https://www.beispiel.ch/konto',
        'client' => json_encode([
            'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36',
            'viewport' => ['w' => 1541, 'h' => 947],
            'screen' => ['w' => 1800, 'h' => 1169],
            'page' => ['w' => 1541, 'h' => 6120],
            'scroll' => ['x' => 0, 'y' => 320],
            'dpr' => 2,
            'colorDepth' => 30,
            'timezone' => 'Europe/Zurich',
            'language' => 'de-CH',
            'maxTouchPoints' => 0,
            'online' => true,
        ]),
        'widget_version' => '1.0.0',
        'elapsed_ms' => 12000,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $payload
 * @param  array<string, string>  $headers
 */
function postFeedback(FeedbackProject $project, array $payload = [], array $headers = [], ?string $ip = null): TestResponse
{
    $test = test();

    if ($ip !== null) {
        $test->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    return $test->post(
        "/v1/projects/{$project->public_key}/feedback",
        feedbackPayload($payload),
        array_merge(['Origin' => TEST_ORIGIN, 'Idempotency-Key' => (string) Str::uuid(), 'Accept' => 'application/json'], $headers),
    );
}

function fakeJpeg(int $width = 320, int $height = 640): UploadedFile
{
    return UploadedFile::fake()->image('screenshot.jpg', $width, $height);
}
