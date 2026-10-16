<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\GeoSource;
use App\Geo\CloudflareGeoLocator;
use App\Geo\GeoLocator;
use App\Geo\MaxMindGeoLocator;
use App\Geo\NullGeoLocator;
use App\Http\Middleware\ResolveProject;
use GeoIp2\Database\Reader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeoLocator::class, function (): GeoLocator {
            return match (GeoSource::fromConfig(config('feedback.geo'))) {
                GeoSource::None => new NullGeoLocator,
                GeoSource::Cloudflare => new CloudflareGeoLocator,
                GeoSource::MaxMind => new MaxMindGeoLocator(function (): ?Reader {
                    static $reader = null;
                    $path = config('feedback.maxmind.database_path');

                    if ($reader === null && is_string($path) && is_file($path)) {
                        $reader = new Reader($path);
                    }

                    return $reader;
                }),
            };
        });
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureActivityLog();
    }

    /**
     * Limits per IP and project, per project and per project and day. Values
     * come from config/feedback.php and the project, without defaults.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('feedback', function (Request $request): Limit|array {
            $project = ResolveProject::project($request);
            $perIp = config('feedback.limits.per_ip_per_minute');
            $perProject = config('feedback.limits.per_project_per_minute');
            $limits = [];

            if (is_int($perIp)) {
                $limits[] = Limit::perMinute($perIp)->by("feedback:ip:{$project->id}:{$request->ip()}");
            }

            if (is_int($perProject)) {
                $limits[] = Limit::perMinute($perProject)->by("feedback:project:{$project->id}");
            }

            if ($project->daily_limit !== null) {
                $limits[] = Limit::perDay($project->daily_limit)->by("feedback:day:{$project->id}");
            }

            return $limits === [] ? Limit::none() : $limits;
        });
    }

    /** Every log entry records how the change was made (via) and by whom (--by). */
    private function configureActivityLog(): void
    {
        Activity::creating(function (Activity $activity): void {
            $activity->properties = ($activity->properties ?? collect())->merge(array_filter([
                'via' => $this->app->runningInConsole() ? 'artisan' : 'http',
                'by' => Context::get('activity_by'),
            ], fn (mixed $value): bool => $value !== null));
        });
    }
}
