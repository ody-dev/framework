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
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application.
    |
    */
    'autoload' => [
        'classmap' => [
            // Add directories to classmap if needed
        ],
        'files' => [
            // Add files to autoload if needed
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

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Routes settings and configuration
    |
    */
    'routes' => [
        'path' => env('ROUTES_PATH', route_path()),
        'middleware' => [
            'global' => [
                Ody\Core\Foundation\Middleware\CorsMiddleware::class,
                Ody\Core\Foundation\Middleware\JsonBodyParserMiddleware::class,
                Ody\Core\Foundation\Middleware\LoggingMiddleware::class,
            ],
        ],
    ],
];