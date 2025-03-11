<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */
    'name' => env('APP_NAME', 'Ody API'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes.
    |
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */
    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the command line tool. You should set this to the root of your
    | application so that it is used when running console commands.
    |
    */
    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions.
    |
    */
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Add your own service providers to
    | this array to grant expanded functionality to your application.
    |
    */
    'providers' => [
        // Core Service Providers
        Ody\Core\Foundation\Providers\ConfigServiceProvider::class,
        Ody\Core\Foundation\Providers\LoggingServiceProvider::class,
        Ody\Core\Foundation\Providers\ApplicationServiceProvider::class,
        Ody\Core\Foundation\Providers\DatabaseServiceProvider::class,
        Ody\Core\Foundation\Providers\MiddlewareServiceProvider::class,
        Ody\Core\Foundation\Providers\RouteServiceProvider::class,

        // Add your application service providers here
        // App\Providers\CustomServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */
    'aliases' => [
        'App' => Ody\Core\Foundation\Application::class,
        'Config' => Ody\Core\Foundation\Support\Config::class,
        'Env' => Ody\Core\Foundation\Support\Env::class,
        'Router' => Ody\Core\Foundation\Router::class,
        'Request' => Ody\Core\Foundation\Http\Request::class,
        'Response' => Ody\Core\Foundation\Http\Response::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Routes settings and configuration
    |
    */
    'routes' => [
        'path' => env('ROUTES_PATH', base_path('routes')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Define middleware settings and available middleware
    |
    */
    'middleware' => [
        // Global middleware applied to all routes
        'global' => [
            Ody\Core\Foundation\Middleware\CorsMiddleware::class,
            Ody\Core\Foundation\Middleware\JsonBodyParserMiddleware::class,
            Ody\Core\Foundation\Middleware\LoggingMiddleware::class,
        ],

        // Named middleware that can be referenced in routes
        'named' => [
            // Authentication middleware with different guards
            'auth' => Ody\Core\Foundation\Middleware\AuthMiddleware::class,
            'auth:api' => Ody\Core\Foundation\Middleware\AuthMiddleware::class,
            'auth:jwt' => Ody\Core\Foundation\Middleware\AuthMiddleware::class,
            'auth:session' => Ody\Core\Foundation\Middleware\AuthMiddleware::class,

            // Role-based access control
            'role' => Ody\Core\Foundation\Middleware\RoleMiddleware::class,
            'role:admin' => Ody\Core\Foundation\Middleware\RoleMiddleware::class,
            'role:user' => Ody\Core\Foundation\Middleware\RoleMiddleware::class,
            'role:guest' => Ody\Core\Foundation\Middleware\RoleMiddleware::class,

            // Rate limiting
            'throttle' => Ody\Core\Foundation\Middleware\ThrottleMiddleware::class,
            'throttle:60,1' => Ody\Core\Foundation\Middleware\ThrottleMiddleware::class,  // 60 requests per minute
            'throttle:1000,60' => Ody\Core\Foundation\Middleware\ThrottleMiddleware::class, // 1000 requests per hour

            // Other middleware
            'cors' => Ody\Core\Foundation\Middleware\CorsMiddleware::class,
            'json' => Ody\Core\Foundation\Middleware\JsonBodyParserMiddleware::class,
            'log' => Ody\Core\Foundation\Middleware\LoggingMiddleware::class,

            // Add your custom middleware here
            // 'cache' => App\Http\Middleware\CacheMiddleware::class,
            // 'csrf' => App\Http\Middleware\CsrfMiddleware::class,
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

        // Middleware resolver options
        'resolvers' => [
            // Add custom resolver classes here if needed
            // 'custom' => App\Http\Middleware\Resolvers\CustomResolver::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Cross-Origin Resource Sharing (CORS) allows browsers to make cross-origin
    | requests from web applications running at one origin to server at another origin.
    |
    */
    'cors' => [
        'origin' => env('CORS_ALLOW_ORIGIN', '*'),
        'methods' => env('CORS_ALLOW_METHODS', 'GET, POST, PUT, DELETE, OPTIONS'),
        'headers' => env('CORS_ALLOW_HEADERS', 'Content-Type, Authorization, X-Requested-With, X-API-Key'),
        'credentials' => env('CORS_ALLOW_CREDENTIALS', false),
        'max_age' => env('CORS_MAX_AGE', 86400), // 24 hours
    ],
];