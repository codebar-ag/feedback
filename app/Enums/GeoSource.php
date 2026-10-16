<?php

declare(strict_types=1);

namespace App\Enums;

enum GeoSource: string
{
    case None = 'none';
    case Cloudflare = 'cloudflare';
    case MaxMind = 'maxmind';

    public static function fromConfig(mixed $value): self
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return match ($value) {
            'cloudflare' => self::Cloudflare,
            'maxmind' => self::MaxMind,
            default => self::None,
        };
    }
}
