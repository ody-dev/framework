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
    $response->end(json_encode([
        'status' => 'ok',
        'timestamp' => time()
    ]));
});

$router->get('/version', function ($request, $response) {
    $response->header('Content-Type', 'application/json');
    $response->end(json_encode([
        'version' => '1.0.0',
        'api' => 'REST API Core',
        'server' => 'Swoole HTTP Server'
    ]));
});

// Auth routes
$router->post('/login', 'App\\Controllers\\AuthController@login');
$router->post('/register', 'App\\Controllers\\AuthController@register');

// User routes with middleware
$router->get('/users', 'App\\Controllers\\UserController@index');

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

    // Products API
    $router->get('/products', 'App\\Controllers\\Api\\ProductController@index');
    $router->get('/products/{id:\\d+}', 'App\\Controllers\\Api\\ProductController@show');

    $router->post('/products', 'App\\Controllers\\Api\\ProductController@store')
        ->middleware('auth:api')
        ->middleware('role:admin');

    $router->put('/products/{id:\\d+}', 'App\\Controllers\\Api\\ProductController@update')
        ->middleware('auth:api')
        ->middleware('role:admin');

    $router->delete('/products/{id:\\d+}', 'App\\Controllers\\Api\\ProductController@destroy')
        ->middleware('auth:api')
        ->middleware('role:admin');

    // Categories API
    $router->get('/categories', 'App\\Controllers\\Api\\CategoryController@index');
    $router->get('/categories/{id:\\d+}', 'App\\Controllers\\Api\\CategoryController@show');
});

// Admin routes
$router->group(['prefix' => '/admin', 'middleware' => ['auth:jwt', 'role:admin']], function ($router) {
    $router->get('/dashboard', 'App\\Controllers\\Admin\\DashboardController@index');
    $router->get('/users', 'App\\Controllers\\Admin\\UserController@index');
    $router->get('/analytics', 'App\\Controllers\\Admin\\AnalyticsController@index');
});