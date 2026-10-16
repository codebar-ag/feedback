<?php

declare(strict_types=1);

use App\Enums\ErrorCode;
use App\Http\Middleware\ApplyProjectCors;
use App\Http\Middleware\EnforceIpPolicy;
use App\Http\Middleware\EnforceOriginPolicy;
use App\Http\Middleware\NoIndex;
use App\Http\Middleware\ResolveProject;
use App\Http\Responses\ApiError;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(RunHealthChecksCommand::class)->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS is applied per project by ApplyProjectCors, the post size check
        // runs inside the route group so that 413 responses carry CORS headers.
        $middleware->remove([HandleCors::class, ValidatePostSize::class]);
        $middleware->append(NoIndex::class);

        // The feedback chain must run before throttle:feedback.
        $middleware->priority([
            ResolveProject::class,
            ApplyProjectCors::class,
            ValidatePostSize::class,
            EnforceOriginPolicy::class,
            EnforceIpPolicy::class,
            ThrottleRequests::class,
            SubstituteBindings::class,
        ]);

        // Behind a load balancer or CDN, list its addresses so that
        // $request->ip() is the client IP (IP policy, rate limits, geo).
        $proxies = env('TRUSTED_PROXIES');

        if (is_string($proxies) && $proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map(trim(...), explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (): bool => true);

        $exceptions->render(function (Throwable $e, Request $request) {
            return match (true) {
                $e instanceof ValidationException => ApiError::response(ErrorCode::ValidationFailed, 422, ['fields' => $e->errors()]),
                $e instanceof ThrottleRequestsException => ApiError::response(ErrorCode::RateLimited, 429, [], array_map(strval(...), $e->getHeaders())),
                $e instanceof PostTooLargeException => ApiError::response(ErrorCode::PayloadTooLarge, 413),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiError::response(ErrorCode::NotFound, 404),
                $e instanceof MethodNotAllowedHttpException => ApiError::response(ErrorCode::MethodNotAllowed, 405, [], array_map(strval(...), $e->getHeaders())),
                $e instanceof HttpExceptionInterface => ApiError::response(ErrorCode::ServerError, $e->getStatusCode()),
                default => ApiError::response(ErrorCode::ServerError, 500),
            };
        });
    })->create();
