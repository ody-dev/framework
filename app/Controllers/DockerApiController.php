<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace App\Controllers;

use App\Services\DockerService;
use Ody\Foundation\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Docker API Controller
 *
 * Provides endpoints for interacting with Docker
 */
class DockerApiController
{
    /**
     * @var DockerService
     */
    private $dockerService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param DockerService $dockerService
     * @param LoggerInterface $logger
     */
    public function __construct(DockerService $dockerService, LoggerInterface $logger)
    {
        $this->dockerService = $dockerService;
        $this->logger = $logger;
    }

    /**
     * Get Docker system information
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function info(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $info = $this->dockerService->getInfo();
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $info
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting Docker info', ['error' => $e->getMessage()]);
            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to get Docker information: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * List containers
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function listContainers(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $queryParams = $request->getQueryParams();
            $all = isset($queryParams['all']) ? (bool)$queryParams['all'] : true;

            $containers = $this->dockerService->listContainers($all);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $containers
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error listing containers', ['error' => $e->getMessage()]);
            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to list containers: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get container details
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function getContainer(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $containerId = $params['id'] ?? null;

            if (!$containerId) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Container ID is required'
                ]);
            }

            $container = $this->dockerService->inspectContainer($containerId);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $container
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting container', [
                'container_id' => $params['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to get container: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get container logs
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function getContainerLogs(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $containerId = $params['id'] ?? null;

            if (!$containerId) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Container ID is required'
                ]);
            }

            $queryParams = $request->getQueryParams();
            $options = [
                'tail' => isset($queryParams['tail']) ? (int)$queryParams['tail'] : 100,
                'timestamps' => isset($queryParams['timestamps']) ? (bool)$queryParams['timestamps'] : true,
                'stdout' => isset($queryParams['stdout']) ? (bool)$queryParams['stdout'] : true,
                'stderr' => isset($queryParams['stderr']) ? (bool)$queryParams['stderr'] : true,
            ];

            $logs = $this->dockerService->getContainerLogs($containerId, $options);

            // Format logs for display
            $formattedLogs = $this->formatContainerLogs($logs);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $formattedLogs
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting container logs', [
                'container_id' => $params['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to get container logs: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Start a container
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function startContainer(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $containerId = $params['id'] ?? null;

            if (!$containerId) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Container ID is required'
                ]);
            }

            $result = $this->dockerService->startContainer($containerId);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => ['started' => $result]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error starting container', [
                'container_id' => $params['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to start container: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Stop a container
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function stopContainer(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $containerId = $params['id'] ?? null;

            if (!$containerId) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Container ID is required'
                ]);
            }

            $queryParams = $request->getQueryParams();
            $timeout = isset($queryParams['timeout']) ? (int)$queryParams['timeout'] : 10;

            $result = $this->dockerService->stopContainer($containerId, $timeout);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => ['stopped' => $result]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error stopping container', [
                'container_id' => $params['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to stop container: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Restart a container
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function restartContainer(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $containerId = $params['id'] ?? null;

            if (!$containerId) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Container ID is required'
                ]);
            }

            $queryParams = $request->getQueryParams();
            $timeout = isset($queryParams['timeout']) ? (int)$queryParams['timeout'] : 10;

            $result = $this->dockerService->restartContainer($containerId, $timeout);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => ['restarted' => $result]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error restarting container', [
                'container_id' => $params['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to restart container: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * List Docker images
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function listImages(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $images = $this->dockerService->listImages();

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $images
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error listing images', ['error' => $e->getMessage()]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to list images: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Format container logs
     *
     * @param string $logs
     * @return array
     */
    private function formatContainerLogs(string $logs): array
    {
        $lines = explode("\n", $logs);
        $formattedLogs = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            // Docker logs format: first byte is stream type (stdout/stderr)
            // We need to remove this header and parse the logs
            $content = mb_substr($line, 8);

            // Check if the line has a timestamp
            if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z) (.*)$/', $content, $matches)) {
                $timestamp = $matches[1];
                $message = $matches[2];

                $formattedLogs[] = [
                    'timestamp' => $timestamp,
                    'message' => $message
                ];
            } else {
                $formattedLogs[] = [
                    'timestamp' => null,
                    'message' => $content
                ];
            }
        }

        return $formattedLogs;
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