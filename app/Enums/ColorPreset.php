<?php

declare(strict_types=1);

namespace App\Enums;

enum ColorPreset: string
{
    case Black = 'black';
    case White = 'white';

    public function hex(): string
    {
        return match ($this) {
            self::Black => '#000000',
            self::White => '#FFFFFF',
        };
    }
}
