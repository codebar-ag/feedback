<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Locale;
use App\Support\ClientData;

/** Validated payload of POST /v1/projects/{key}/feedback. */
final readonly class FeedbackSubmission
{
    /**
     * @param  list<mixed>|null  $consoleLog
     * @param  list<mixed>|null  $networkLog
     * @param  list<mixed>|null  $annotations
     * @param  array<string, mixed>|null  $customData
     * @param  array{mime: string, width: int, height: int, bytes: int, path: string}|null  $screenshot
     */
    public function __construct(
        public string $idempotencyKey,
        public string $message,
        public string $email,
        public ?string $name,
        public ?string $identityUserId,
        public Locale $locale,
        public string $pageUrl,
        public ?string $pageTitle,
        public ?string $referrer,
        public ClientData $client,
        public ?array $consoleLog,
        public ?array $networkLog,
        public ?array $annotations,
        public ?array $customData,
        public ?array $screenshot,
        public ?string $screenshotError,
        public ?string $submittedFromUrl,
        public string $widgetVersion,
        public int $elapsedMs,
    ) {}
}
