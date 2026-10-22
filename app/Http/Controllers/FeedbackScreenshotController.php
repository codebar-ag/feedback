<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Screenshot behind a signed link, inline or as download (?download=1). */
final class FeedbackScreenshotController
{
    public function __invoke(Request $request, FeedbackEntry $entry): Response
    {
        $shot = $entry->screenshot;

        if ($shot === null || $shot->data === null) {
            throw new NotFoundHttpException;
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return new Response($shot->data, 200, [
            'Content-Type' => $shot->mime,
            'Content-Length' => (string) strlen($shot->data),
            'Content-Disposition' => "{$disposition}; filename=\"feedback-{$entry->id}.{$shot->extension()}\"",
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
