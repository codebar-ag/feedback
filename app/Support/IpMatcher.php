<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\IpUtils;

/** Checks a client IP against single addresses and CIDR ranges (IPv4, IPv6). */
final readonly class IpMatcher
{
    /** @param list<string> $allowed empty list means "no restriction" */
    public function __construct(private array $allowed) {}

    public function allows(?string $ip): bool
    {
        if ($this->allowed === []) {
            return true;
        }

        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return IpUtils::checkIp($ip, $this->allowed);
    }

    public static function isValid(string $entry): bool
    {
        $parts = explode('/', $entry);

        if (count($parts) > 2) {
            return false;
        }

        $address = $parts[0];
        $isV4 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isV6 = ! $isV4 && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if (! $isV4 && ! $isV6) {
            return false;
        }

        if (! isset($parts[1])) {
            return true;
        }

        if (preg_match('/^\d{1,3}$/', $parts[1]) !== 1) {
            return false;
        }

        return (int) $parts[1] <= ($isV4 ? 32 : 128);
    }
}
