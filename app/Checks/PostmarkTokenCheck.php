<?php

declare(strict_types=1);

namespace App\Checks;

use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Asks Postmark for the server that belongs to the configured server
 * token. Fails when the token is missing or rejected, before mails pile up
 * in failed jobs. Reports ok when the mailer is not postmark.
 */
final class PostmarkTokenCheck extends Check
{
    public function run(): Result
    {
        $mailer = config('mail.default');

        if ($mailer !== 'postmark') {
            return Result::make()->shortSummary('mailer: '.(is_string($mailer) ? $mailer : 'none'))->ok();
        }

        $token = config('services.postmark.token') ?? config('services.postmark.key');
        $token = is_string($token) ? $token : '';
        $result = Result::make();

        if ($token === '') {
            return $result->shortSummary('no token')->failed('MAIL_MAILER is postmark, but no Postmark server token is configured.');
        }

        try {
            $response = Http::withHeaders(['X-Postmark-Server-Token' => $token])
                ->acceptJson()
                ->timeout(3)
                ->get('https://api.postmarkapp.com/server');
        } catch (Throwable $exception) {
            return $result->shortSummary('postmark unreachable')->failed("Postmark request failed: {$exception->getMessage()}");
        }

        if ($response->successful()) {
            return $result->shortSummary('token valid')->ok();
        }

        $message = $response->json('Message');

        return $result->shortSummary("http {$response->status()}")
            ->failed('Postmark rejected the server token: '.(is_string($message) && $message !== '' ? $message : $response->status()).'.');
    }
}
