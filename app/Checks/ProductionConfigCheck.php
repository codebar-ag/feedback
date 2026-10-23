<?php

declare(strict_types=1);

namespace App\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Fails on settings that silently break a production app: an APP_URL that
 * is not https or still points at localhost (wrong links in every mail and
 * redirect), and cache or session stores that forget everything between
 * requests. The mailer is not checked: `log` is the default when an app
 * sends no mail.
 */
final class ProductionConfigCheck extends Check
{
    public function run(): Result
    {
        $problems = [];

        $url = config('app.url');
        $url = is_string($url) ? $url : '';
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! str_starts_with($url, 'https://')) {
            $problems[] = "APP_URL is not https ({$url})";
        }

        if (in_array($host, ['', 'localhost', '127.0.0.1'], true) || str_ends_with($host, '.test')) {
            $problems[] = "APP_URL points at a local host ({$url})";
        }

        if (config('cache.default') === 'array') {
            $problems[] = 'CACHE_STORE is array';
        }

        if (config('session.driver') === 'array') {
            $problems[] = 'SESSION_DRIVER is array';
        }

        $result = Result::make()->shortSummary($problems === [] ? 'ok' : count($problems).' problem(s)');

        return $problems === [] ? $result->ok() : $result->failed(implode('; ', $problems).'.');
    }
}
