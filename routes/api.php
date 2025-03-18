<?php

/**
 * Main application routes
 *
 * This file contains all the routes for the application.
 * Variables $router, $middleware, and $container are available from the RouteLoader.
 */

use Ody\Foundation\Facades\Route;
use Ody\Foundation\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Public authentication endpoints
Route::post('/auth/login', 'App\Controllers\AuthController@login');
Route::post('/auth/register', 'App\Controllers\AuthController@register');
Route::post('/auth/refresh', 'App\Controllers\AuthController@refresh');

// Protected authentication endpoints
Route::group(['prefix' => '/auth', 'middleware' => ['auth']], function ($router) {
    $router->get('/user', 'App\Controllers\AuthController@user');
    $router->post('/logout', 'App\Controllers\AuthController@logout');
});

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