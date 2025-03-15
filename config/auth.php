<?php
/*
 * This file is part of ODY framework
 *
 * @link https://ody.dev
 * @documentation https://ody.dev/docs
 * @license https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application.
    |
    */

    'defaults' => [
        'guard' => 'web',
        'provider' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'token',
            'provider' => 'users',
            'hash' => false,
        ],

        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
            'expiration' => 60 * 24 * 30, // 30 days in minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'database',
            'model' => App\Models\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal Access Tokens
    |--------------------------------------------------------------------------
    |
    | Settings related to personal access tokens.
    |
    */

    'token_prefix' => '',

    'token_expiration' => 60 * 24 * 30, // 30 days in minutes

    'middleware' => [
        'authenticate' => \Ody\Auth\Middleware\Authenticate::class,
        'attach_user' => \Ody\Auth\Middleware\AttachUserToRequest::class,
    ],
];