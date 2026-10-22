<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Browser login to Nightwatch (OAuth 2.1 + PKCE, the same flow MCP clients use) for NightwatchIssuesCheck.
 * Writes the access token (valid one year) to NIGHTWATCH_API_TOKEN in .env; it is never printed.
 */
class NightwatchLoginCommand extends Command
{
    protected $signature = 'nightwatch:login {--timeout=300 : Seconds to wait for the browser login}';

    protected $description = 'Log in to Nightwatch in the browser and write NIGHTWATCH_API_TOKEN to .env for the issues health check.';

    public function handle(): int
    {
        $baseUrl = config()->string('default.nightwatch.url');

        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($server === false) {
            $this->components->error("Could not open a local callback port: {$errorMessage}");

            return Command::FAILURE;
        }

        $port = (int) Str::afterLast((string) stream_socket_get_name($server, false), ':');
        $redirectUri = "http://127.0.0.1:{$port}/callback";

        $registration = Http::acceptJson()->post($baseUrl.'/oauth/register', [
            'client_name' => config()->string('app.name').' health check',
            'redirect_uris' => [$redirectUri],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]);

        if ($registration->failed()) {
            $this->components->error("Client registration failed ({$registration->status()}): {$registration->body()}");

            return Command::FAILURE;
        }

        $clientId = $registration->json('client_id');
        $clientId = is_string($clientId) ? $clientId : '';
        $verifier = Str::random(64);
        $state = Str::random(32);

        $authorizeUrl = $baseUrl.'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'mcp:use',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'resource' => $baseUrl.'/mcp',
        ]);

        $this->components->info('Log in to Nightwatch in the browser window that just opened. If none opened, visit:');
        $this->line($authorizeUrl);

        if (PHP_OS_FAMILY === 'Darwin') {
            Process::run(['open', $authorizeUrl]);
        }

        $query = $this->waitForCallback($server, (int) $this->option('timeout'));

        if ($query === null) {
            $this->components->error('No browser callback received in time.');

            return Command::FAILURE;
        }

        if (($query['state'] ?? null) !== $state || ! isset($query['code'])) {
            $this->components->error('Login was cancelled or the callback was invalid: '.($query['error'] ?? 'state mismatch'));

            return Command::FAILURE;
        }

        $token = Http::asForm()->acceptJson()->post($baseUrl.'/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $query['code'],
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'code_verifier' => $verifier,
            'resource' => $baseUrl.'/mcp',
        ]);

        if ($token->failed()) {
            $this->components->error("Token exchange failed ({$token->status()}): {$token->body()}");

            return Command::FAILURE;
        }

        $accessToken = $token->json('access_token');
        $expiresIn = $token->json('expires_in');

        $this->writeEnv('NIGHTWATCH_API_TOKEN', is_string($accessToken) ? $accessToken : '');

        $this->components->info('NIGHTWATCH_API_TOKEN written to .env, valid until '.now()->addSeconds(is_numeric($expiresIn) ? (int) $expiresIn : 0)->toDateString().'. Copy it to the server environment.');

        return Command::SUCCESS;
    }

    /**
     * Set the key in .env, replacing an existing line or appending it.
     */
    protected function writeEnv(string $key, string $value): void
    {
        $path = $this->laravel->environmentFilePath();
        $contents = file_exists($path) ? (string) file_get_contents($path) : '';
        $line = "{$key}={$value}";

        $contents = preg_match("/^{$key}=.*$/m", $contents)
            ? (string) preg_replace("/^{$key}=.*$/m", $line, $contents)
            : rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";

        file_put_contents($path, $contents);
    }

    /**
     * Accept connections on the local port until the OAuth redirect arrives, then return its query parameters.
     *
     * @param  resource  $server
     * @return array<string, string>|null
     */
    protected function waitForCallback($server, int $timeout): ?array
    {
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            $connection = @stream_socket_accept($server, max(1, $deadline - time()));

            if ($connection === false) {
                continue;
            }

            $requestLine = (string) fgets($connection);
            $path = (string) (explode(' ', $requestLine)[1] ?? '');

            if (! str_starts_with($path, '/callback')) {
                fwrite($connection, "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
                fclose($connection);

                continue;
            }

            $body = '<h1>Nightwatch login received</h1><p>You can close this window.</p>';
            fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body);
            fclose($connection);

            parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

            /** @var array<string, string> $query */
            return $query;
        }

        return null;
    }
}
