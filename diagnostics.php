<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

// Define app base path
define('APP_BASE_PATH', __DIR__);

// Autoload dependencies
require APP_BASE_PATH . '/vendor/autoload.php';

use Ody\Foundation\Bootstrap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

// Initialize diagnostic logging
$logFile = APP_BASE_PATH . '/storage/logs/diagnostic.log';
file_put_contents($logFile, "=== Diagnostic Run: " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);

function logMessage(string $message)
{
    global $logFile;
    file_put_contents($logFile, $message . "\n", FILE_APPEND);
    echo $message . "\n";
}

// Initialize application with diagnostic mode
logMessage("Initializing application...");
try {
    $app = Bootstrap::init();
    logMessage("Application initialized successfully.");
} catch (\Throwable $e) {
    logMessage("ERROR: Failed to initialize application: " . $e->getMessage());
    logMessage("File: " . $e->getFile() . " (Line: " . $e->getLine() . ")");
    logMessage("Stack Trace:\n" . $e->getTraceAsString());
    exit(1);
}

// Test routes to diagnose
$testRoutes = [
    '/debug',
    '/debug-error',
    '/debug-response',
    '/health',
    '/version',
    '/users',
];

// Create PSR-17 factory
$psr17Factory = new Psr17Factory();

// Get logger
$logger = $app->getContainer()->make(LoggerInterface::class);
$logger->info('Diagnostic tool started');

// Test each route
foreach ($testRoutes as $route) {
    logMessage("\nTesting route: " . $route);

    try {
        // Create a request
        $request = new ServerRequest('GET', $route);

        // Send request to application
        logMessage("  Sending request...");
        $response = $app->handleRequest($request);

        // Check response
        if ($response instanceof ResponseInterface) {
            $statusCode = $response->getStatusCode();
            $body = (string)$response->getBody();
            $contentType = $response->getHeaderLine('Content-Type');

            logMessage("  Response received: HTTP " . $statusCode);
            logMessage("  Content-Type: " . $contentType);

            if ($contentType === 'application/json') {
                // Try to decode and format JSON response
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    logMessage("  Body (JSON): " . json_encode($data, JSON_PRETTY_PRINT));
                } else {
                    logMessage("  Body: " . substr($body, 0, 500) . (strlen($body) > 500 ? '...' : ''));
                }
            } else {
                logMessage("  Body: " . substr($body, 0, 500) . (strlen($body) > 500 ? '...' : ''));
            }

            logMessage("  Result: SUCCESS");
        } else {
            logMessage("  ERROR: Invalid response type: " . (is_object($response) ? get_class($response) : gettype($response)));
        }
    } catch (\Throwable $e) {
        logMessage("  EXCEPTION: " . $e->getMessage());
        logMessage("  File: " . $e->getFile() . " (Line: " . $e->getLine() . ")");
        logMessage("  Stack Trace:\n" . $e->getTraceAsString());
    }
}

logMessage("\nDiagnostic complete.");

