<?php

/**
 * Main application routes
 *
 * This file contains all the routes for the application.
 * Variables $router, $middleware, and $container are available from the RouteLoader.
 */

use Ody\Foundation\Facades\Route;

Route::get('/users', 'App\Controllers\UserController@index');
Route::get('/users/{id}', 'App\Controllers\UserController@find');
