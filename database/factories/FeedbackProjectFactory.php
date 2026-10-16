<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedbackProject;
use App\Support\PublicKeyGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FeedbackProject> */
class FeedbackProjectFactory extends Factory
{
    protected $model = FeedbackProject::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'public_key' => PublicKeyGenerator::generate(),
            'theme' => ['primary' => '#0A7C66'],
            'allowed_origins' => ['https://www.beispiel.ch'],
            'allowed_ips' => [],
            'is_active' => true,
            'daily_limit' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
