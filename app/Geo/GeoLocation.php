<?php

declare(strict_types=1);

namespace App\Geo;

final readonly class GeoLocation
{
    public function __construct(
        public ?string $country,
        public ?string $city,
        public ?string $postcode,
    ) {}

    public function isEmpty(): bool
    {
        return $this->country === null && $this->city === null && $this->postcode === null;
    }
}
