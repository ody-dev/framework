<?php

return [
    /*
    |--------------------------------------------------------------------------
    | InfluxDB 2.x Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines the configuration settings for the InfluxDB 2.x integration.
    |
    */

    // Connection settings
    'url' => env('INFLUXDB_URL', 'http://127.0.0.1:8086'),
    'token' => env('INFLUXDB_TOKEN', ''),
    'org' => env('INFLUXDB_ORG', 'organization'),
    'bucket' => env('INFLUXDB_BUCKET', 'logs'),

    // Logging settings
    'log_level' => env('INFLUXDB_LOG_LEVEL', 'debug'),
    'measurement' => env('INFLUXDB_MEASUREMENT', 'logs'),

    // Batching settings
    'batch_size' => env('INFLUXDB_BATCH_SIZE', 1000),
    'flush_interval' => env('INFLUXDB_FLUSH_INTERVAL', 1000), // milliseconds

    // Coroutine support (for Swoole)
    'use_coroutines' => env('INFLUXDB_USE_COROUTINES', false),

    // Default tags to include with all log entries
    'tags' => [
        'service' => env('APP_NAME', 'ody-service'),
        'environment' => env('APP_ENV', 'production'),
        'instance' => env('INSTANCE_ID', gethostname()),
    ],

    // API endpoints for log retrieval
    'api' => [
        'enabled' => env('INFLUXDB_API_ENABLED', true),
        'route_prefix' => env('INFLUXDB_API_PREFIX', '/api/logs'),
        'middleware' => ['auth:api'],
    ],
];