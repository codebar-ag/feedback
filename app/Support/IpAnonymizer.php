<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\IpMode;
use Symfony\Component\HttpFoundation\IpUtils;

/** Applies the configured IP storage mode (full or anonymized). */
final readonly class IpAnonymizer
{
    public static function apply(?string $ip, IpMode $mode): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return match ($mode) {
            IpMode::Full => $ip,
            // IPv4: last octet, IPv6: last 80 bits (keeps the /48 prefix).
            IpMode::Anonymized => IpUtils::anonymize($ip, 1, 10),
        };
    }
}
