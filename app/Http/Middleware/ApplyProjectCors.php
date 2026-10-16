<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Http\Responses\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dynamic CORS per project (concept 5.4). Answers the preflight and sets
 * Access-Control-Allow-Origin to exactly the requesting origin, but only if
 * the project allows it. Never "*", never with credentials.
 */
final class ApplyProjectCors
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $allowed = $origin !== null && ResolveProject::project($request)->originMatcher()->allows($origin);

        if ($request->isMethod('OPTIONS')) {
            $response = $allowed
                ? new IlluminateResponse('', 204)
                : ApiError::response(ErrorCode::OriginNotAllowed, 403);
        } else {
            $response = $next($request);
        }

        $response->headers->set('Vary', 'Origin', false);

        if ($allowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Idempotency-Key');
            $response->headers->set('Access-Control-Expose-Headers', 'Retry-After');
            $response->headers->set('Access-Control-Max-Age', '600');
        }

        return $response;
    }
}
