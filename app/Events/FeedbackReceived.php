<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\FeedbackEntry;
use Illuminate\Foundation\Events\Dispatchable;

/** Extension point for notifications (mail, Slack, ticket system). */
final readonly class FeedbackReceived
{
    use Dispatchable;

    public function __construct(public FeedbackEntry $entry) {}
}
