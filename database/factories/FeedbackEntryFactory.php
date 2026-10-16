<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedbackEntry;
use App\Models\FeedbackProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FeedbackEntry> */
class FeedbackEntryFactory extends Factory
{
    protected $model = FeedbackEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => FeedbackProject::factory(),
            'client_request_id' => (string) Str::uuid(),
            'source' => 'widget',
            'name' => null,
            'email' => $this->faker->safeEmail(),
            'message' => $this->faker->sentence(),
            'locale' => 'de_CH',
            'page_url' => 'https://www.beispiel.ch/',
            'page_title' => 'Beispiel',
            'origin' => 'https://www.beispiel.ch',
            'ip' => '203.0.113.7',
            'version_precision' => 'approximate',
            'widget_version' => '1.0.0',
        ];
    }

    public function completed(): static
    {
        return $this->state(['completed_at' => now()]);
    }
}
