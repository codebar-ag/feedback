<?php

declare(strict_types=1);

use Spatie\Activitylog\Models\Activity;

return [

    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    'default_log_name' => 'feedback',

    'default_auth_driver' => null,

    'include_soft_deleted_subjects' => false,

    'activity_model' => Activity::class,

    'default_except_attributes' => [],

    'buffer' => [
        'enabled' => false,
    ],

];
