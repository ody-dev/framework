<?php

/**
 * REST API Entry Point with PSR-7 and PSR-15 Support
 */

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Define app root path
define('APP_ROOT', realpath(__DIR__ . '/../src/'));

// Bootstrap application and get container
$container = require_once __DIR__ . '/../src/Foundation/bootstrap.php';

// Get application instance
$app = $container->make('Ody\\Core\\Foundation\\Application');

// Run the application
$app->run();