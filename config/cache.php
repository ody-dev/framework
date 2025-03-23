<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache connection that will be used by
    | the framework. This connection is used when another is not explicitly
    | specified when executing a cache operation.
    |
    | Supported: "array", "redis", "memcached"
    |
    */
    'default' => env('CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all the cache "stores" for your application as
    | well as their drivers.
    |
    */
    'drivers' => [
        'array' => [
            'ttl' => 3600, // Default TTL in seconds
        ],

        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'auth' => env('REDIS_PASSWORD', null),
            'db' => env('REDIS_DB', 0),
            'prefix' => env('CACHE_PREFIX', 'ody_cache:'),
            'ttl' => env('CACHE_TTL', 3600),
            'options' => [
                // Redis specific options
            ],
        ],

        'memcached' => [
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID', ''),
            'prefix' => env('CACHE_PREFIX', 'ody_cache:'),
            'ttl' => env('CACHE_TTL', 3600),
            'options' => [
                // Memcached specific options
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When using the "memcached" or "redis" cache drivers, you may want all
    | cached values to be prefixed to avoid conflicts with other applications
    | using the same cache servers. This is applied when not specified in
    | the driver configuration.
    |
    */
    'prefix' => env('CACHE_PREFIX', 'ody_'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Default time-to-live for cached items in seconds when not specified
    | during cache operations.
    |
    */
    'ttl' => env('CACHE_TTL', 3600),
];