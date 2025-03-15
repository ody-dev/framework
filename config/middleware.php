<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware are run during every request to your application.
    |
    */

    'global' => [
        \Ody\Foundation\Middleware\JsonBodyParserMiddleware::class,
        \Ody\Foundation\Middleware\CorsMiddleware::class,
        \Ody\Foundation\Middleware\LoggingMiddleware::class,
        \Ody\Auth\Middleware\AttachUserToRequest::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware may be assigned to groups or used individually.
    |
    */

    'named' => [
        'auth' => \Ody\Auth\Middleware\Authenticate::class,
        'auth.sanctum' => \Ody\Auth\Middleware\Authenticate::class,
        'auth.token' => \Ody\Auth\Middleware\Authenticate::class,
        'abilities' => \Ody\Auth\Middleware\CheckAbilities::class,
        'ability' => \Ody\Auth\Middleware\CheckForAnyAbility::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Groups
    |--------------------------------------------------------------------------
    |
    | Middleware groups allow you to apply multiple middleware to a route at once.
    |
    */

    'groups' => [
        'web' => [
            // Web middleware
        ],

        'api' => [
            'throttle:60,1',
        ],
    ],
];