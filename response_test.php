<?php
// Save as response-test.php in your project root

// Define app base path
define('APP_BASE_PATH', __DIR__);

// Autoload dependencies
require APP_BASE_PATH . '/vendor/autoload.php';

use Ody\Foundation\Http\Response;
use Psr\Http\Message\ResponseInterface;

// Create log file
$logFile = APP_BASE_PATH . '/storage/logs/response-test.log';
file_put_contents($logFile, "=== Response Test: " . date('Y-m-d H:i:s') . " ===\n");

function log_msg($message) {
    global $logFile;
    file_put_contents($logFile, $message . "\n", FILE_APPEND);
    echo $message . "\n";
}

// Test 1: Basic Response creation and withJson
log_msg("=== Test 1: Basic Response and withJson ===");
try {
    $response = new Response();

    log_msg("Response class: " . get_class($response));
    log_msg("Implements ResponseInterface: " . (($response instanceof ResponseInterface) ? 'Yes' : 'No'));

    // Test withJson
    $data = ['name' => 'Test', 'value' => 123];
    $jsonResponse = $response->withJson($data);

    log_msg("withJson return class: " . get_class($jsonResponse));
    log_msg("withJson implements ResponseInterface: " . (($jsonResponse instanceof ResponseInterface) ? 'Yes' : 'No'));

    // Check content
    log_msg("Content-Type: " . $jsonResponse->getHeaderLine('Content-Type'));
    log_msg("Body: " . (string)$jsonResponse->getBody());

    log_msg("Test 1 passed");
} catch (\Throwable $e) {
    log_msg("Test 1 ERROR: " . $e->getMessage());
    log_msg("  File: " . $e->getFile() . " (Line: " . $e->getLine() . ")");
}

// Test 2: Test with UserController jsonResponse method
log_msg("\n=== Test 2: UserController jsonResponse method ===");
try {
    // Create a simplified version of the jsonResponse method
    function testJsonResponse(ResponseInterface $response, $data): ResponseInterface {
        $response = $response->withHeader('Content-Type', 'application/json');

        if ($response instanceof Response) {
            return $response->withJson($data);
        }

        $response->getBody()->write(json_encode($data));
        return $response;
    }

    $response = new Response();
    $data = ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'];

    $result = testJsonResponse($response, $data);

    log_msg("jsonResponse return class: " . get_class($result));
    log_msg("jsonResponse implements ResponseInterface: " . (($result instanceof ResponseInterface) ? 'Yes' : 'No'));
    log_msg("Content-Type: " . $result->getHeaderLine('Content-Type'));
    log_msg("Body: " . (string)$result->getBody());

    log_msg("Test 2 passed");
} catch (\Throwable $e) {
    log_msg("Test 2 ERROR: " . $e->getMessage());
    log_msg("  File: " . $e->getFile() . " (Line: " . $e->getLine() . ")");
}

// Test 3: Manual test without using withJson
log_msg("\n=== Test 3: Manual JSON response creation ===");
try {
    $response = new Response();
    $data = ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'];

    // Create a JSON response manually
    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    $json = json_encode($data);
    $stream = $factory->createStream($json);

    $result = $response
        ->withHeader('Content-Type', 'application/json')
        ->withBody($stream);

    log_msg("Manual creation return class: " . get_class($result));
    log_msg("Implements ResponseInterface: " . (($result instanceof ResponseInterface) ? 'Yes' : 'No'));
    log_msg("Content-Type: " . $result->getHeaderLine('Content-Type'));
    log_msg("Body: " . (string)$result->getBody());

    log_msg("Test 3 passed");
} catch (\Throwable $e) {
    log_msg("Test 3 ERROR: " . $e->getMessage());
    log_msg("  File: " . $e->getFile() . " (Line: " . $e->getLine() . ")");
}

log_msg("\nResponse testing completed");