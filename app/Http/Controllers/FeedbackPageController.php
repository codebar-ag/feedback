<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackEntry;
use App\Support\FeedbackLink;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Read-only page of one feedback entry behind a signed link (feedback:link).
 * The only HTML the API serves: no scripts, no external resources.
 */
final class FeedbackPageController
{
    public function __invoke(Request $request, FeedbackEntry $entry): Response
    {
        $expires = Carbon::createFromTimestamp($request->integer('expires'));
        $by = $request->query('by');
        // Metadata only, the BLOB is served by the screenshot link.
        $shot = $entry->screenshot()->select(['entry_id', 'mime', 'width', 'height', 'bytes'])->first();
        $nonce = Str::random(32);

        return response()->view('feedback.show', [
            'entry' => $entry,
            'project' => $entry->project,
            'shot' => $shot,
            'imageUrl' => $shot === null ? null : FeedbackLink::screenshot($entry, $expires),
            'downloadUrl' => $shot === null ? null : FeedbackLink::screenshot($entry, $expires, download: true),
            'pageHref' => FeedbackLink::safeHref($entry->page_url),
            'sentFromHref' => FeedbackLink::safeHref($entry->submitted_from_url),
            'console' => self::rows($entry->console_log, ['type', 'message', 'count']),
            'network' => self::rows($entry->network_log, ['method', 'url', 'status', 'duration', 'count']),
            'customData' => $entry->custom_data === null || $entry->custom_data === [] ? null : (string) json_encode($entry->custom_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'statusUrl' => $request->boolean('manage') ? FeedbackLink::status($entry, $expires, is_string($by) ? $by : null) : null,
            'expires' => $expires,
            'nonce' => $nonce,
        ])->withHeaders([
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'nonce-{$nonce}'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Logs come from the browser: every cell becomes a plain string.
     *
     * @param  array<mixed>|null  $log
     * @param  list<string>  $keys
     * @return list<array<string, string>>
     */
    private static function rows(?array $log, array $keys): array
    {
        $rows = [];

        foreach ($log ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $row = [];

            foreach ($keys as $key) {
                $value = $item[$key] ?? null;
                $row[$key] = is_scalar($value) ? (string) $value : '';
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
