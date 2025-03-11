<?php

/**
 * REST API Entry Point
 */

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Define app root path
define('APP_ROOT', realpath(__DIR__ . '/..'));

// Bootstrap application and get container
$container = require_once __DIR__ . '/bootstrap.php';

// Get application instance
$app = $container->make('Ody\\Core\\Application');

// Start the server
$app->start();
