<?php

/**
 * Main application routes
 *
 * This file contains all the routes for the application.
 * Variables $router, $middleware, and $container are available from the RouteLoader.
 */

use Ody\Foundation\Facades\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ody\Foundation\Http\Response;

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
Route::get('/users', 'App\Controllers\UserController@index');

Route::get('/users/{id:\\d+}', 'App\Controllers\UserController@show')
    ->middleware('auth:api');

Route::post('/users', 'App\Controllers\UserController@store')
    ->middleware('auth:api')
    ->middleware('role:admin');

Route::put('/users/{id:\\d+}', 'App\Controllers\UserController@update')
    ->middleware('auth:api')
    ->middleware('role:admin');

Route::delete('/users/{id:\\d+}', 'App\Controllers\UserController@destroy')
    ->middleware('auth:api')
    ->middleware('role:admin');

// API route groups
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

Route::get('/api/logs/recent', 'Ody\InfluxDB\Controllers\InfluxDBLogViewerController@recent');
Route::get('/api/logs/services', 'Ody\InfluxDB\Controllers\InfluxDBLogViewerController@services');
Route::get('/api/logs/levels', 'Ody\InfluxDB\Controllers\InfluxDBLogViewerController@levels');