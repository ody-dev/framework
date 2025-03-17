<?php

/**
 * Main application routes
 *
 * This file contains all the routes for the application.
 * Variables $router, $middleware, and $container are available from the RouteLoader.
 */

use Ody\Foundation\Facades\Route;
use Ody\Foundation\Http\Response;
use Ody\Foundation\Middleware\AuthMiddleware;
use Ody\Foundation\Middleware\ThrottleMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Public routes
Route::get('/health', function (ServerRequestInterface $request, ResponseInterface $response) {
    $response = $response->withHeader('Content-Type', 'application/json');

    if ($response instanceof Response) {
        return $response->withJson([
            'status' => 'ok',
            'timestamp' => time()
        ]);
    }

    $response->getBody()->write(json_encode([
        'status' => 'ok',
        'timestamp' => time()
    ]));

    return $response;
});

Route::get('/version', function (ServerRequestInterface $request, ResponseInterface $response) {
    // Make sure we're returning a ResponseInterface
    $data = [
        'version' => '1.0.0',
        'api' => 'REST API Core with PSR-7/15 Support',
        'server' => 'HTTP Server'
    ];

    // Method 1: Use withJson() for a Response instance
    if ($response instanceof \Ody\Foundation\Http\Response) {
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withJson($data);
    }

    // Method 2: Fallback for any PSR-7 implementation
    $response = $response->withHeader('Content-Type', 'application/json');
    $response->getBody()->write(json_encode($data));
    return $response;
});

// User routes with middleware
Route::get('/users', 'App\Controllers\UserController@index')
    ->middleware(AuthMiddleware::class, ThrottleMiddleware::class);

Route::get('/users/{id:\\d+}', 'App\Controllers\UserController@show')
    ->middleware('auth:api');

Route::post('/users', 'App\Controllers\UserController@store');

Route::put('/users/{id:\\d+}', 'App\Controllers\UserController@update');

Route::delete('/users/{id:\\d+}', 'App\Controllers\UserController@destroy');

// Public authentication endpoints
Route::post('/api/auth/login', 'App\Controllers\AuthController@login');
Route::post('/api/auth/register', 'App\Controllers\AuthController@register');

// Protected authentication endpoints
Route::group(['prefix' => '/api/auth', 'middleware' => ['auth:jwt']], function ($router) {
    $router->get('/user', 'App\Controllers\AuthController@user');
    $router->post('/logout', 'App\Controllers\AuthController@logout');
});

// API route groups
// TODO: review middleware handling for grouped routes
Route::group(['prefix' => '/api/v1', 'middleware' => ['throttle:60,1']], function ($router) {
    // API routes will be defined here
    $router->get('/status', function (ServerRequestInterface $request, ResponseInterface $response) {
        $response = $response->withHeader('Content-Type', 'application/json');

        $data = [
            'status' => 'operational',
            'time' => date('c'),
            'api_version' => 'v1'
        ];

        if ($response instanceof Response) {
            return $response->withJson($data);
        }

        $response->getBody()->write(json_encode($data));
        return $response;
    });
});