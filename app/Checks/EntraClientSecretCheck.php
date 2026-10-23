<?php

declare(strict_types=1);

namespace App\Checks;

use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Requests a client-credentials token from Microsoft Entra ID. Fails when
 * the client secret is rejected: Entra secrets expire after at most 24
 * months, and SSO then fails for every user at once. Reads the credentials
 * from codebar-ag/laravel-microsoft-entra-sso or from socialite's
 * services.microsoft, and reports ok when the app has no SSO configured.
 */
final class EntraClientSecretCheck extends Check
{
    /** @var list<array{0: string, 1: string}> config key and tenant key, in lookup order */
    private const array SOURCES = [['microsoft-entra-sso', 'tenant_id'], ['services.microsoft', 'tenant']];

    /** Placeholder secrets some configs fall back to when SSO is not set up. */
    private const array PLACEHOLDER_SECRETS = ['', 'not-configured'];

    public function run(): Result
    {
        $result = Result::make();
        $credentials = $this->credentials();

        if ($credentials === null) {
            return $result->shortSummary('no sso configured')->ok();
        }

        [$tenant, $clientId, $clientSecret] = $credentials;

        try {
            $response = Http::asForm()->timeout(3)->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ]);
        } catch (Throwable $exception) {
            return $result->shortSummary('entra unreachable')->failed("Entra request failed: {$exception->getMessage()}");
        }

        if ($response->successful()) {
            return $result->shortSummary('secret valid')->ok();
        }

        $error = $response->json('error');
        $error = is_string($error) ? $error : '';

        return $result->shortSummary($error !== '' ? $error : "http {$response->status()}")
            ->failed('Entra rejected the client credentials ('.($error ?: $response->status()).'). Create a new client secret and update the environment.');
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function credentials(): ?array
    {
        foreach (self::SOURCES as [$configKey, $tenantKey]) {
            $tenant = $this->configString("{$configKey}.{$tenantKey}");
            $clientId = $this->configString("{$configKey}.client_id");
            $clientSecret = $this->configString("{$configKey}.client_secret");

            if ($tenant !== '' && $clientId !== '' && ! in_array($clientSecret, self::PLACEHOLDER_SECRETS, true)) {
                return [$tenant, $clientId, $clientSecret];
            }
        }

        return null;
    }

    private function configString(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }
}
