<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Http\Responses\ApiError;
use App\Models\FeedbackProject;
use App\Support\PublicKeyGenerator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/** Public key in the path → active project. Unknown and inactive look identical (404). */
final class ResolveProject
{
    public const string ATTRIBUTE = 'feedback_project';

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $route = $route instanceof Route ? $route : null;
        $key = $route?->parameter('publicKey');

        $project = is_string($key) && PublicKeyGenerator::isValid($key)
            ? FeedbackProject::query()->active()->where('public_key', $key)->first()
            : null;

        if ($project === null) {
            Log::notice('feedback.rejected', ['reason' => ErrorCode::ProjectNotFound->value]);

            return ApiError::response(ErrorCode::ProjectNotFound, 404);
        }

        // Only the resolved project is passed on, not the raw key.
        $route->forgetParameter('publicKey');

        $request->attributes->set(self::ATTRIBUTE, $project);
        $request->attributes->set('feedback_project_id', $project->id);

        return $next($request);
    }

    public static function project(Request $request): FeedbackProject
    {
        $project = $request->attributes->get(self::ATTRIBUTE);

        if (! $project instanceof FeedbackProject) {
            throw new LogicException('ResolveProject middleware has not run.');
        }

        return $project;
    }
}
