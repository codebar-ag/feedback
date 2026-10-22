<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FeedbackEntry;
use DateTimeInterface;
use Illuminate\Support\Facades\URL;

/**
 * Signed, expiring links to the feedback page and its screenshot. Team links
 * (manage) can also complete and reopen the entry, the optional name is
 * recorded in the activity log. Anyone with the link can use it until it
 * expires; only rotating APP_KEY revokes links early.
 */
final readonly class FeedbackLink
{
    public const int DEFAULT_MINUTES = 7 * 24 * 60;

    public const int MAX_MINUTES = 30 * 24 * 60;

    public static function page(FeedbackEntry $entry, DateTimeInterface $expires, bool $manage = false, ?string $by = null): string
    {
        return URL::temporarySignedRoute('feedback.page', $expires, ['entry' => $entry->id, ...self::access($manage, $by)]);
    }

    /** Form target for completing and reopening, only for team links. */
    public static function status(FeedbackEntry $entry, DateTimeInterface $expires, ?string $by = null): string
    {
        return URL::temporarySignedRoute('feedback.status', $expires, ['entry' => $entry->id, ...self::access(true, $by)]);
    }

    public static function screenshot(FeedbackEntry $entry, DateTimeInterface $expires, bool $download = false): string
    {
        return URL::temporarySignedRoute('feedback.screenshot', $expires, ['entry' => $entry->id, ...($download ? ['download' => 1] : [])]);
    }

    /** @return array<string, string|int> */
    private static function access(bool $manage, ?string $by): array
    {
        if (! $manage) {
            return [];
        }

        $by = trim((string) $by);

        return ['manage' => 1, ...($by !== '' ? ['by' => $by] : [])];
    }

    /** Only http(s) URLs become clickable links, anything else stays text. */
    public static function safeHref(?string $url): ?string
    {
        return is_string($url) && preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
