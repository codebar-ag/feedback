<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveProject;
use App\Http\Resources\ProjectConfigResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProjectConfigController
{
    public function __invoke(Request $request): JsonResponse
    {
        $maxAge = config('feedback.config_max_age');

        return (new ProjectConfigResource(ResolveProject::project($request)))
            ->response()
            ->header('Cache-Control', 'public, max-age='.(is_int($maxAge) ? $maxAge : 0));
    }
}
