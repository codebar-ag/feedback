<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PrecisionMode;

final readonly class ParsedAgent
{
    public function __construct(
        public ?string $osName,
        public ?string $osVersion,
        public ?string $browserName,
        public ?string $browserVersion,
        public ?string $deviceType,
        public PrecisionMode $precision,
    ) {}
}
