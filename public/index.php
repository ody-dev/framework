<?php

/**
 * REST API Entry Point with PSR-7 and PSR-15 Support
 */

// Define app base path
define('APP_BASE_PATH', realpath(__DIR__ . '/..'));

// Autoload dependencies
require APP_BASE_PATH . '/vendor/autoload.php';

use Ody\Core\Foundation\Application;
use Ody\Core\Foundation\Support\Env;

// Load environment variables
(new Env(APP_BASE_PATH))->load(
        $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: null
);

// Set error handling based on environment
$debug = env('APP_DEBUG', false);
ini_set('display_errors', $debug ? 1 : 0);
error_reporting($debug ? E_ALL : E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);

// Handle PHP errors during development
if ($debug) {
    set_error_handler(function($severity, $message, $file, $line) {
        if (error_reporting() & $severity) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }
    });
}

try {
    // Bootstrap application and get container
    $container = require_once APP_BASE_PATH . '/src/Foundation/bootstrap.php';

    // Get application instance
    $app = $container->make(Application::class);

    // Run the application
    $app->run();
} catch (\Throwable $e) {
    // Handle uncaught exceptions
    if ($debug) {
        // Show detailed error in development
        echo "<h1>Application Error</h1>";
        echo "<p><strong>Type:</strong> " . get_class($e) . "</p>";
        echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
        echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
        echo "<h2>Stack Trace:</h2>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    } else {
        // Show generic error in production
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal Server Error']);
    }

    // Log the error regardless of environment
    if (class_exists('Ody\Core\Foundation\Logger')) {
        $logger = new \Ody\Core\Foundation\Logger(APP_BASE_PATH . '/storage/logs/error.log');
        $logger->error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}