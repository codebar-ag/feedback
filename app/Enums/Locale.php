<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case DeCh = 'de_CH';
    case FrCh = 'fr_CH';
    case EnCh = 'en_CH';
    case ItCh = 'it_CH';

    /** BCP-47 tag, e.g. "de-CH". */
    public function bcp47(): string
    {
        return str_replace('_', '-', $this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $locale): string => $locale->value, self::cases());
    }
}
