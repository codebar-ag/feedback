<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API is only for the widget (CORS requests) and for monitoring. Typing
 * a URL into a browser is a navigation and gets an empty 403.
 */
final class BlockBrowserNavigation
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->get('Sec-Fetch-Mode') === 'navigate' || $request->headers->get('Sec-Fetch-Dest') === 'document') {
            return new IlluminateResponse('', 403);
        }

        return $next($request);
    }
}
