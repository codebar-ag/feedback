<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackEntry;
use App\Support\FeedbackLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;

/** Completes or reopens an entry from a team link (feedback:link), then shows the page again. */
final class FeedbackStatusController
{
    public function __invoke(Request $request, FeedbackEntry $entry): RedirectResponse|Response
    {
        // Links shown to the sender after sending are read-only.
        if (! $request->boolean('manage')) {
            return new Response('', 403);
        }

        $request->validate(['status' => ['required', 'in:open,completed']]);

        $by = $request->query('by');
        Context::add('activity_by', is_string($by) && $by !== '' ? $by : 'signed link');

        $completed = $request->input('status') === 'completed';

        if ($completed !== ($entry->completed_at !== null)) {
            $entry->completed_at = $completed ? now() : null;
            $entry->save();
        }

        $expires = Carbon::createFromTimestamp($request->integer('expires'));

        return new RedirectResponse(FeedbackLink::page($entry, $expires, manage: true, by: is_string($by) ? $by : null), 303);
    }
}
