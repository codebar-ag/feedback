<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\FeedbackSubmission;
use App\Enums\IpMode;
use App\Events\FeedbackReceived;
use App\Geo\GeoLocator;
use App\Models\FeedbackEntry;
use App\Models\FeedbackProject;
use App\Models\FeedbackScreenshot;
use App\Support\IpAnonymizer;
use App\Support\UserAgentParser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stores one feedback in a single transaction: entry, screenshot and the
 * idempotency check on (project_id, client_request_id).
 */
final readonly class StoreFeedback
{
    public function __construct(
        private GeoLocator $geo,
        private UserAgentParser $agents,
    ) {}

    /** @return array{entry: FeedbackEntry, created: bool} */
    public function handle(FeedbackProject $project, FeedbackSubmission $submission, Request $request): array
    {
        $existing = $this->find($project, $submission->idempotencyKey);

        if ($existing !== null) {
            return ['entry' => $existing, 'created' => false];
        }

        $attributes = $this->attributes($project, $submission, $request);

        try {
            $entry = DB::transaction(function () use ($attributes, $submission): FeedbackEntry {
                $entry = FeedbackEntry::query()->create($attributes);

                if ($submission->screenshot !== null) {
                    $data = file_get_contents($submission->screenshot['path']);

                    if ($data === false) {
                        throw new RuntimeException('Screenshot upload is not readable.');
                    }

                    FeedbackScreenshot::query()->create([
                        'entry_id' => $entry->id,
                        'mime' => $submission->screenshot['mime'],
                        'width' => $submission->screenshot['width'],
                        'height' => $submission->screenshot['height'],
                        'bytes' => strlen($data),
                        'sha256' => hash('sha256', $data),
                        'data' => $data,
                    ]);
                }

                return $entry;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Concurrent retry with the same Idempotency-Key.
            return ['entry' => $this->find($project, $submission->idempotencyKey) ?? throw $e, 'created' => false];
        }

        FeedbackReceived::dispatch($entry);

        return ['entry' => $entry, 'created' => true];
    }

    private function find(FeedbackProject $project, string $key): ?FeedbackEntry
    {
        return FeedbackEntry::query()
            ->where('project_id', $project->id)
            ->where('client_request_id', $key)
            ->first();
    }

    /** @return array<string, mixed> */
    private function attributes(FeedbackProject $project, FeedbackSubmission $submission, Request $request): array
    {
        $ip = $request->ip();
        $mode = IpMode::tryFrom(is_string($mode = config('feedback.ip_mode')) ? $mode : '') ?? IpMode::Full;
        $location = $ip !== null ? $this->geo->locate($request, $ip) : null;
        $userAgent = $request->userAgent() ?? $submission->client->userAgent;
        $agent = $this->agents->parse($userAgent, $submission->client->uaData);
        $client = $submission->client;

        return [
            'project_id' => $project->id,
            'client_request_id' => $submission->idempotencyKey,
            'source' => 'widget',
            'name' => $submission->name,
            'email' => $submission->email,
            'message' => $submission->message,
            'locale' => $submission->locale->value,
            'identity_user_id' => $submission->identityUserId,
            'page_url' => $submission->pageUrl,
            'page_title' => $submission->pageTitle,
            'referrer' => $submission->referrer,
            'origin' => (string) $request->headers->get('Origin'),
            'ip' => IpAnonymizer::apply($ip, $mode),
            'geo_country' => $location?->country,
            'geo_city' => $location?->city,
            'geo_postcode' => $location?->postcode,
            'os_name' => $agent->osName,
            'os_version' => $agent->osVersion,
            'browser_name' => $agent->browserName,
            'browser_version' => $agent->browserVersion,
            'device_type' => $agent->deviceType,
            'version_precision' => $agent->precision->value,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 2048) : null,
            'viewport_w' => $client->viewportW,
            'viewport_h' => $client->viewportH,
            'screen_w' => $client->screenW,
            'screen_h' => $client->screenH,
            'page_w' => $client->pageW,
            'page_h' => $client->pageH,
            'scroll_x' => $client->scrollX,
            'scroll_y' => $client->scrollY,
            'dpr' => $client->dpr,
            'color_depth' => $client->colorDepth,
            'timezone' => $client->timezone,
            'browser_language' => $client->language,
            'console_log' => $submission->consoleLog,
            'network_log' => $submission->networkLog,
            'annotations' => $submission->annotations,
            'custom_data' => $submission->customData,
            'screenshot_error' => $submission->screenshotError,
            'submitted_from_url' => $submission->submittedFromUrl,
            'widget_version' => $submission->widgetVersion,
            'elapsed_ms' => $submission->elapsedMs,
        ];
    }
}
