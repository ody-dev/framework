<?php

/**
 * Main application routes
 *
 * This file contains all the routes for the application.
 * Variables $router, $middleware, and $container are available from the RouteLoader.
 */

// Public routes
$router->get('/health', function ($request, $response) {
    $response->header('Content-Type', 'application/json');
    $response->json()->withJson([
        'status' => 'ok',
        'timestamp' => time()
    ]);
});

$router->get('/version', function ($request, $response) {
    $response->header('Content-Type', 'application/json');
    return $response->json()->withJson([
        'version' => '1.0.0',
        'api' => 'REST API Core',
        'server' => 'HTTP Server'
    ]);
});

// User routes with middleware
$router->get('/users', 'App\\Controllers\\UserController@index')->middleware('auth:api');

$router->get('/users/{id:\\d+}', 'App\\Controllers\\UserController@show')
    ->middleware('auth:api');

$router->post('/users', 'App\\Controllers\\UserController@store')
    ->middleware('auth:api')
    ->middleware('role:admin');

$router->put('/users/{id:\\d+}', 'App\\Controllers\\UserController@update')
    ->middleware('auth:api')
    ->middleware('role:admin');

$router->delete('/users/{id:\\d+}', 'App\\Controllers\\UserController@destroy')
    ->middleware('auth:api')
    ->middleware('role:admin');

// API route groups
$router->group(['prefix' => '/api/v1', 'middleware' => ['throttle:60,1']], function ($router) {

});

// Admin routes
$router->group(['prefix' => '/admin', 'middleware' => ['auth:jwt', 'role:admin']], function ($router) {

});