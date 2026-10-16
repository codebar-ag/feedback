<?php

declare(strict_types=1);

namespace App\Enums;

enum IpMode: string
{
    case Full = 'full';
    case Anonymized = 'anonymized';
}
