<?php

declare(strict_types=1);

use App\Http\Controllers\FeedbackPageController;
use App\Http\Controllers\FeedbackScreenshotController;
use App\Http\Controllers\FeedbackStatusController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProjectConfigController;
use App\Http\Controllers\StoreFeedbackController;
use App\Http\Middleware\ApplyProjectCors;
use App\Http\Middleware\EnforceIpPolicy;
use App\Http\Middleware\EnforceOriginPolicy;
use App\Http\Middleware\ResolveProject;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/up', HealthController::class)->name('health');

Route::prefix('v1/projects/{publicKey}')
    ->middleware([
        ResolveProject::class,
        ApplyProjectCors::class,
        ValidatePostSize::class,
        EnforceOriginPolicy::class,
        EnforceIpPolicy::class,
    ])
    ->group(function (): void {
        // Answered by ApplyProjectCors, the action is never reached.
        Route::options('feedback', fn (): Response => new Response('', 204))->name('feedback.preflight');
        Route::post('feedback', StoreFeedbackController::class)->middleware('throttle:feedback')->name('feedback.store');
        Route::get('config', ProjectConfigController::class)->name('feedback.config');
    });

// Signed, expiring links from feedback:link: the only pages meant for a browser.
Route::prefix('v1/feedback/{entry}')
    ->middleware('signed')
    ->group(function (): void {
        Route::get('/', FeedbackPageController::class)->name('feedback.page');
        Route::get('screenshot', FeedbackScreenshotController::class)->name('feedback.screenshot');
        Route::post('status', FeedbackStatusController::class)->name('feedback.status');
    });
