<?php

return [
    'name' => env('APP_NAME', 'Ody API'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'providers' => [
        Ody\Foundation\Providers\ErrorServiceProvider::class,
        Ody\Auth\Providers\AuthServiceProvider::class,
        Ody\DB\DatabaseServiceProvider::class,

        // Add your application service providers here
        // App\Providers\CustomServiceProvider::class,
    ],

    'aliases' => [
        'App' => Ody\Foundation\Application::class,
        'Config' => \Ody\Support\Config::class,
        'Env' => \Ody\Support\Env::class,
        'Router' => Ody\Foundation\Router::class,
        'Request' => Ody\Foundation\Http\Request::class,
        'Response' => Ody\Foundation\Http\Response::class,
    ],

    'routes' => [
        'path' => env('ROUTES_PATH', base_path('routes')),
    ],

    'middleware' => [
        // Global middleware applied to all routes
        'global' => [
            \Ody\Foundation\Middleware\ErrorHandlerMiddleware::class,
            \Ody\Foundation\Middleware\CorsMiddleware::class,
            \Ody\Foundation\Middleware\JsonBodyParserMiddleware::class,
            \Ody\Foundation\Middleware\LoggingMiddleware::class,
            \Ody\Auth\Middleware\AttachUserToRequest::class,
        ],

        // Named middleware that can be referenced in routes
        'named' => [
            'auth' => \Ody\Auth\Middleware\Authenticate::class,
            'auth.sanctum' => \Ody\Auth\Middleware\Authenticate::class,
            'auth.token' => \Ody\Auth\Middleware\Authenticate::class,
            'abilities' => \Ody\Auth\Middleware\CheckAbilities::class,
            'ability' => \Ody\Auth\Middleware\CheckForAnyAbility::class,
            'throttle' => \Ody\Foundation\Middleware\ThrottleMiddleware::class,
        ],

        // Middleware groups for route groups
        'groups' => [
            'web' => [
                'auth',
                'json',
            ],
            'api' => [
                'throttle:60,1',
                'auth:api',
                'json',
            ],
            'admin' => [
                'auth',
                'role:admin',
                'json',
            ],
        ],
    ],

    'cors' => [
        'origin' => env('CORS_ALLOW_ORIGIN', '*'),
        'methods' => env('CORS_ALLOW_METHODS', 'GET, POST, PUT, DELETE, OPTIONS'),
        'headers' => env('CORS_ALLOW_HEADERS', 'Content-Type, Authorization, X-Requested-With, X-API-Key'),
        'credentials' => env('CORS_ALLOW_CREDENTIALS', false),
        'max_age' => env('CORS_MAX_AGE', 86400), // 24 hours
    ],
];