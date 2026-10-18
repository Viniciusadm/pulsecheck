<?php

return [

    'registration_enabled' => (bool) env('PULSECHECK_REGISTRATION_ENABLED', true),

    'max_monitors_per_user' => (int) env('PULSECHECK_MAX_MONITORS', 20),

    'allowed_intervals' => array_map('intval', explode(',', env('PULSECHECK_ALLOWED_INTERVALS', '1,5,10'))),

    'default_interval' => (int) env('PULSECHECK_DEFAULT_INTERVAL', 5),

    'timeout_min' => (int) env('PULSECHECK_TIMEOUT_MIN', 1),

    'timeout_max' => (int) env('PULSECHECK_TIMEOUT_MAX', 30),

    'timeout_default' => (int) env('PULSECHECK_TIMEOUT_DEFAULT', 10),

    'history_retention_days' => (int) env('PULSECHECK_HISTORY_RETENTION_DAYS', 30),

    'failures_to_down' => (int) env('PULSECHECK_FAILURES_TO_DOWN', 2),

    'successes_to_up' => (int) env('PULSECHECK_SUCCESSES_TO_UP', 2),

    'allow_private_targets' => (bool) env('PULSECHECK_ALLOW_PRIVATE_TARGETS', false),

    'max_url_length' => 2048,

    'max_redirects' => (int) env('PULSECHECK_MAX_REDIRECTS', 5),

    'blocked_hostnames' => [
        'metadata',
        'metadata.google.internal',
        'metadata.goog',
        'metadata.azure.internal',
        'instance-data',
        'instance-data.ec2.internal',
    ],

];
