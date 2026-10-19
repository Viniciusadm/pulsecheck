<?php

return [

    'setup_token' => env('PULSECHECK_SETUP_TOKEN'),

    'generate_setup_token' => (bool) env('PULSECHECK_GENERATE_SETUP_TOKEN', true),

    'locales' => ['en', 'pt-BR'],

    'default_locale' => 'en',

    'allowed_intervals' => array_map('intval', explode(',', env('PULSECHECK_ALLOWED_INTERVALS', '1,5,10'))),

    'default_interval' => (int) env('PULSECHECK_DEFAULT_INTERVAL', 5),

    'heartbeat_allowed_intervals' => array_map('intval', explode(',', env('PULSECHECK_HEARTBEAT_INTERVALS', '1,5,10,15,30,60,360,720,1440'))),

    'timeout_min' => (int) env('PULSECHECK_TIMEOUT_MIN', 1),

    'timeout_max' => min((int) env('PULSECHECK_TIMEOUT_MAX', 30), 60),

    'timeout_default' => (int) env('PULSECHECK_TIMEOUT_DEFAULT', 10),

    'history_retention_days' => (int) env('PULSECHECK_HISTORY_RETENTION_DAYS', 30),

    'uptime_rollup_retention_days' => (int) env('PULSECHECK_UPTIME_ROLLUP_RETENTION_DAYS', 400),

    'failures_to_down' => (int) env('PULSECHECK_FAILURES_TO_DOWN', 2),

    'successes_to_up' => (int) env('PULSECHECK_SUCCESSES_TO_UP', 2),

    'reminder_intervals' => array_map('intval', explode(',', env('PULSECHECK_REMINDER_INTERVALS', '15,30,60,120,240'))),

    'degraded_to_confirm' => (int) env('PULSECHECK_DEGRADED_TO_CONFIRM', 2),

    'max_request_body_bytes' => 16384,

    'keyword_max_body_bytes' => 1048576,

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
