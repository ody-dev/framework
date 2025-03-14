<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace App\Controllers;

use InfluxDB\Database;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ody\Foundation\Http\Response;
use Psr\Log\LoggerInterface;

/**
 * Log Viewer Controller
 *
 * Provides endpoints for retrieving logs from InfluxDB
 */
class LogViewerController
{
    /**
     * @var Database InfluxDB database
     */
    private $database;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * LogViewerController constructor
     *
     * Dependencies are automatically injected by the container
     */
    public function __construct(Database $database, LoggerInterface $logger)
    {
        $this->database = $database;
        $this->logger = $logger;
    }

    /**
     * Get recent logs
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function recent(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            // Get query parameters
            $queryParams = $request->getQueryParams();

            // Default time range is last 5 minutes
            $timeRange = $queryParams['timeRange'] ?? '5m';

            // Optional service filter
            $service = $queryParams['service'] ?? null;

            // Optional level filter
            $level = $queryParams['level'] ?? null;

            // Build the InfluxDB query
            $query = "SELECT * FROM logs WHERE time > now() - {$timeRange}";

            // Add service filter if specified
            if ($service) {
                $query .= " AND service = '{$service}'";
            }

            // Add level filter if specified
            if ($level) {
                $query .= " AND level = '{$level}'";
            }

            // Order by time descending and limit results
            $limit = $queryParams['limit'] ?? 100;
            $query .= " ORDER BY time DESC LIMIT {$limit}";

            // Execute the query
            $result = $this->database->query($query);

            // Process results
            $logs = [];
            foreach ($result->getPoints() as $point) {
                // Convert InfluxDB point to a more client-friendly format
                $logs[] = [
                    'timestamp' => $point['time'],
                    'level' => $point['level'] ?? 'unknown',
                    'service' => $point['service'] ?? 'unknown',
                    'message' => $point['message'] ?? '',
                    'context' => $this->extractContext($point),
                ];
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'logs' => $logs,
                'count' => count($logs),
                'query' => [
                    'timeRange' => $timeRange,
                    'service' => $service,
                    'level' => $level,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving logs', ['error' => $e->getMessage()]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to retrieve logs: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get available log services
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function services(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            // Query for distinct service values
            $query = "SHOW TAG VALUES FROM logs WITH KEY = service";
            $result = $this->database->query($query);

            $services = [];
            foreach ($result->getPoints() as $point) {
                $services[] = $point['value'];
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'services' => $services,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving services', ['error' => $e->getMessage()]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to retrieve services: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get available log levels
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function levels(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            // Query for distinct level values
            $query = "SHOW TAG VALUES FROM logs WITH KEY = level";
            $result = $this->database->query($query);

            $levels = [];
            foreach ($result->getPoints() as $point) {
                $levels[] = $point['value'];
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'levels' => $levels,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving log levels', ['error' => $e->getMessage()]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to retrieve log levels: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract context fields from an InfluxDB point
     *
     * @param array $point
     * @return array
     */
    private function extractContext(array $point): array
    {
        $context = [];

        // Extract all fields that aren't standard log fields
        $standardFields = ['time', 'level', 'service', 'message', 'environment'];

        foreach ($point as $key => $value) {
            if (!in_array($key, $standardFields)) {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    /**
     * Helper method to create JSON responses
     *
     * @param ResponseInterface $response
     * @param mixed $data
     * @return ResponseInterface
     */
    private function jsonResponse(ResponseInterface $response, $data): ResponseInterface
    {
        // Always set JSON content type
        $response = $response->withHeader('Content-Type', 'application/json');

        // If using our custom Response class
        if ($response instanceof Response) {
            return $response->withJson($data);
        }

        // For other PSR-7 implementations
        $response->getBody()->write(json_encode($data));
        return $response;
    }
}