<?php

return [
    'name' => env('APP_NAME', 'Ody API'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'providers' => [
        \Ody\Foundation\Providers\FacadeServiceProvider::class,
        \Ody\Foundation\Providers\MiddlewareServiceProvider::class,
        \Ody\Foundation\Providers\RouteServiceProvider::class,
        \Ody\Foundation\Providers\ErrorServiceProvider::class,
        \Ody\Server\Providers\ServerServiceProvider::class,
        \Ody\DB\Providers\DatabaseServiceProvider::class,

        // Add your application service providers here
        // App\Providers\CustomServiceProvider::class,
    ],
    'aliases' => [
        'App' => \Ody\Foundation\Application::class,
        'Config' => \Ody\Support\Config::class,
        'Env' => \Ody\Support\Env::class,
        'Router' => \Ody\Foundation\Router\Router::class,
        'Request' => \Ody\Foundation\Http\Request::class,
        'Response' => \Ody\Foundation\Http\Response::class,
    ],
    'routes' => [
        'path' => env('ROUTES_PATH', base_path('routes')),
    ],
    'middleware' => [
        'global' => [
            // TODO: revision error handling
//            \Ody\Foundation\Middleware\ErrorHandlerMiddleware::class,
            \Ody\Foundation\Middleware\CorsMiddleware::class,
            \Ody\Foundation\Middleware\JsonBodyParserMiddleware::class,
//            \App\Middleware\RequestLoggerMiddleware::class,
        ],
        'named' => [
            'auth' => \Ody\Foundation\Middleware\AuthMiddleware::class,
        ],
        'groups' => [
            'api' => [
                \Ody\Foundation\Middleware\AuthMiddleware::class,
                \Ody\Foundation\Middleware\ThrottleMiddleware::class,
            ]
        ]
    ],
    'cors' => [
        'origin' => env('CORS_ALLOW_ORIGIN', '*'),
        'methods' => env('CORS_ALLOW_METHODS', 'GET, POST, PUT, DELETE, OPTIONS'),
        'headers' => env('CORS_ALLOW_HEADERS', 'Content-Type, Authorization, X-Requested-With, X-API-Key'),
        'credentials' => env('CORS_ALLOW_CREDENTIALS', false),
        'max_age' => env('CORS_MAX_AGE', 86400), // 24 hours
    ],
];
