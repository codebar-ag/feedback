<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FeedbackProject;
use App\Support\PublicKeyGenerator;

final readonly class RotatePublicKey
{
    public function handle(FeedbackProject $project): string
    {
        do {
            $key = PublicKeyGenerator::generate();
        } while (FeedbackProject::query()->where('public_key', $key)->exists());

        $project->public_key = $key;
        $project->save();

        return $key;
    }
}
