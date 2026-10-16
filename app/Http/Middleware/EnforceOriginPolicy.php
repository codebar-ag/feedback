<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Http\Responses\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Requests without an allowed Origin header are rejected with 403. */
final class EnforceOriginPolicy
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $project = ResolveProject::project($request);

        if (! $project->originMatcher()->allows($request->headers->get('Origin'))) {
            Log::notice('feedback.rejected', ['reason' => 'origin_not_allowed', 'project' => $project->id]);

            return ApiError::response(ErrorCode::OriginNotAllowed, 403);
        }

        return $next($request);
    }
}
