<?php

// Public authentication endpoints
use Ody\Foundation\Facades\Route;

Route::post('/auth/login', 'App\Controllers\AuthController@login');
Route::post('/auth/register', 'App\Controllers\AuthController@register');

// Protected authentication endpoints
Route::group(['prefix' => '/api/auth', 'middleware' => ['auth']], function ($router) {
    $router->get('/user', 'App\Controllers\AuthController@user');
    $router->post('/logout', 'App\Controllers\AuthController@logout');
});