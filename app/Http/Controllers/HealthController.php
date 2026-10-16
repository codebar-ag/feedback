<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackProject;
use Illuminate\Http\JsonResponse;
use Throwable;

/** GET /up: 200 when the database answers, 503 otherwise. No details. */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            FeedbackProject::query()->limit(1)->exists();
        } catch (Throwable) {
            return new JsonResponse(['status' => 'down'], 503);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
