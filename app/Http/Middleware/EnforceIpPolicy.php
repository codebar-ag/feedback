<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Http\Responses\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the client IP against the project's allowed IPs/CIDR ranges.
 * Responds with the same 403 body as the origin check.
 */
final class EnforceIpPolicy
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $project = ResolveProject::project($request);

        if (! $project->ipMatcher()->allows($request->ip())) {
            Log::notice('feedback.rejected', ['reason' => 'ip_not_allowed', 'project' => $project->id]);

            return ApiError::response(ErrorCode::OriginNotAllowed, 403);
        }

        return $next($request);
    }
}
