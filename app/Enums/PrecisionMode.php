<?php

declare(strict_types=1);

namespace App\Enums;

enum PrecisionMode: string
{
    case Exact = 'exact';
    case Approximate = 'approximate';
}
