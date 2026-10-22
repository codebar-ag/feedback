<?php

declare(strict_types=1);

use Spatie\Health\Notifications\Notifiable;
use Spatie\Health\ResultStores\CacheHealthResultStore;

return [

    'result_stores' => [
        CacheHealthResultStore::class => [
            'store' => 'file',
        ],
    ],

    'notifications' => [
        'enabled' => false,
        'notifications' => [],
        'notifiable' => Notifiable::class,
        'throttle_notifications_for_minutes' => 60,
        'throttle_notifications_key' => 'health:latestNotificationSentAt:',
        'slack' => [
            'webhook_url' => env('HEALTH_SLACK_WEBHOOK_URL', ''),
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],
    ],

    /*
     * Oh Dear application health monitoring: https://ohdear.app/docs/features/application-health-monitoring
     */
    'oh_dear_endpoint' => [
        'enabled' => (bool) env('OH_DEAR_HEALTH_CHECK_ENABLED', true),
        'always_send_fresh_results' => true,
        'secret' => env('OH_DEAR_HEALTH_CHECK_SECRET'),
        'url' => env('OH_DEAR_HEALTH_CHECK_PATH', '/oh-dear-health-check-results'),
    ],

    'theme' => 'light',

    'silence_health_queue_job' => true,

];
