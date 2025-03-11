<?php

/**
 * Main application routes
 *
 * This file contains all the routes for the application.
 * Variables $router, $middleware, and $container are available from the RouteLoader.
 */

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Middleware\AuthMiddleware;
use Ody\Core\Foundation\Middleware\RoleMiddleware;

// Public routes
$router->get('/health', function (ServerRequestInterface $request, ResponseInterface $response) {
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

$router->get('/version', function (ServerRequestInterface $request, ResponseInterface $response) {
    $response = $response->withHeader('Content-Type', 'application/json');

    $data = [
        'version' => '1.0.0',
        'api' => 'REST API Core with PSR-7/15 Support',
        'server' => 'HTTP Server'
    ];

    if ($response instanceof Response) {
        return $response->withJson($data);
    }

    $response->getBody()->write(json_encode($data));
    return $response;
});

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

// Admin routes
$router->group(['prefix' => '/admin', 'middleware' => ['auth:jwt', 'role:admin']], function ($router) {
    // Admin routes will be defined here
    $router->get('/dashboard', function (ServerRequestInterface $request, ResponseInterface $response) {
        $response = $response->withHeader('Content-Type', 'application/json');

        $data = [
            'admin' => true,
            'dashboard' => 'Admin Dashboard',
            'stats' => [
                'users' => 150,
                'active' => 120,
                'new_today' => 5
            ]
        ];

        if ($response instanceof Response) {
            return $response->withJson($data);
        }

        $response->getBody()->write(json_encode($data));
        return $response;
    });
});