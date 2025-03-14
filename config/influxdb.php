<?php

return [
    /*
    |--------------------------------------------------------------------------
    | InfluxDB Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines the configuration settings for the InfluxDB integration.
    |
    */

    // Connection settings
    'host' => env('INFLUXDB_HOST', 'localhost'),
    'port' => env('INFLUXDB_PORT', 8086),
    'username' => env('INFLUXDB_USERNAME', ''),
    'password' => env('INFLUXDB_PASSWORD', ''),
    'database' => env('INFLUXDB_DATABASE', 'logs'),

    // Logging settings
    'log_level' => env('INFLUXDB_LOG_LEVEL', 'debug'),
    'measurement' => env('INFLUXDB_MEASUREMENT', 'logs'),

    // Batching settings
    'batch_size' => env('INFLUXDB_BATCH_SIZE', 10),

    // Coroutine support (for Swoole)
    'use_coroutines' => env('INFLUXDB_USE_COROUTINES', true),

    // Default tags to include with all log entries
    'tags' => [
        'service' => env('APP_NAME', 'ody-service'),
        'environment' => env('APP_ENV', 'production'),
        'instance' => env('INSTANCE_ID', gethostname()),
    ],

    // Database creation and retention
    'ensure_db_exists' => env('INFLUXDB_ENSURE_DB_EXISTS', true),
    'retention_policy' => [
        'duration' => env('INFLUXDB_RETENTION_DURATION', '30d'),
        'replication' => env('INFLUXDB_RETENTION_REPLICATION', '1'),
        'default' => true,
    ],

    // API endpoints for log retrieval
    'api' => [
        'enabled' => env('INFLUXDB_API_ENABLED', true),
        'route_prefix' => env('INFLUXDB_API_PREFIX', '/api/logs'),
        'middleware' => ['auth:api'],
    ],
];