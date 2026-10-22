<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FeedbackEntry;
use App\Support\FeedbackLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeedbackEntry */
final class FeedbackReceivedResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(private readonly FeedbackEntry $entry)
    {
        parent::__construct($entry);
    }

    /** @return array{id: string, status: string, url?: string, url_expires_at?: string} */
    public function toArray(Request $request): array
    {
        $minutes = config('feedback.share_link_minutes');

        if (! is_int($minutes) || $minutes < 1) {
            return ['id' => $this->entry->id, 'status' => 'received'];
        }

        // Signed link to the sender's own entry, shown on the thank you screen.
        $expires = now()->addMinutes($minutes)->startOfSecond();

        return [
            'id' => $this->entry->id,
            'status' => 'received',
            'url' => FeedbackLink::page($this->entry, $expires),
            'url_expires_at' => $expires->toIso8601String(),
        ];
    }
}
