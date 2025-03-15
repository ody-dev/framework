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
use App\Services\DockerStackService;
use Ody\Foundation\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Docker Stack API Controller
 *
 * Provides endpoints for interacting with Docker Stacks
 */
class DockerStackApiController
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
    public function __construct(DockerStackService $dockerService, LoggerInterface $logger)
    {
        $this->dockerService = $dockerService;
        $this->logger = $logger;
    }

    /**
     * List all stacks
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function listStacks(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $stacks = $this->dockerService->listStacks();

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $stacks
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error listing stacks', ['error' => $e->getMessage()]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to list stacks: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get stack details
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function getStack(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $stackName = $params['name'] ?? null;

            if (!$stackName) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Stack name is required'
                ]);
            }

            $stack = $this->dockerService->getStack($stackName);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $stack
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting stack details', [
                'stack' => $params['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to get stack details: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get stack compose file
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function getStackComposeFile(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $stackName = $params['name'] ?? null;

            if (!$stackName) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Stack name is required'
                ]);
            }

            $composeFile = $this->dockerService->getStackComposeFile($stackName);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $composeFile
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting stack compose file', [
                'stack' => $params['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to get stack compose file: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Create a new stack
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function createStack(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $data = $request->getParsedBody() ?? [];

            // Validate required fields
            if (empty($data['name'])) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Stack name is required'
                ]);
            }

            if (empty($data['composeContent'])) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Compose file content is required'
                ]);
            }

            // Prepare options
            $options = [
                'orchestrator' => $data['orchestrator'] ?? 'swarm',
                'resolveImage' => $data['options']['resolveImage'] ?? 'always',
                'prune' => $data['options']['prune'] ?? true,
            ];

            // Create stack
            $result = $this->dockerService->deployStack(
                $data['name'],
                $data['composeContent'],
                $data['envVars'] ?? [],
                $options
            );

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error creating stack', [
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to create stack: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update a stack
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function updateStack(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $stackName = $params['name'] ?? null;
            $data = $request->getParsedBody() ?? [];

            if (!$stackName) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Stack name is required'
                ]);
            }

            if (empty($data['composeContent'])) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Compose file content is required'
                ]);
            }

            // Prepare options
            $options = [
                'orchestrator' => $data['orchestrator'] ?? 'swarm',
                'resolveImage' => $data['options']['resolveImage'] ?? 'always',
                'prune' => $data['options']['prune'] ?? true,
            ];

            // Update stack
            $result = $this->dockerService->updateStack(
                $stackName,
                $data['composeContent'],
                $data['envVars'] ?? [],
                $options
            );

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error updating stack', [
                'stack' => $params['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to update stack: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete a stack
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function deleteStack(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $stackName = $params['name'] ?? null;

            if (!$stackName) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Stack name is required'
                ]);
            }

            $result = $this->dockerService->removeStack($stackName);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => ['removed' => $result]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error deleting stack', [
                'stack' => $params['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to delete stack: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Redeploy a stack
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function redeployStack(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $stackName = $params['name'] ?? null;

            if (!$stackName) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Stack name is required'
                ]);
            }

            // First get the compose file
            $composeFile = $this->dockerService->getStackComposeFile($stackName);

            // Then redeploy with the same compose file
            $result = $this->dockerService->deployStack($stackName, $composeFile, [], [
                'orchestrator' => 'swarm', // Use the same orchestrator as original
                'resolveImage' => 'always',
                'prune' => true
            ]);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error redeploying stack', [
                'stack' => $params['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to redeploy stack: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get stack service logs
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function getStackServiceLogs(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        try {
            $stackName = $params['stack'] ?? null;
            $serviceName = $params['service'] ?? null;

            if (!$stackName || !$serviceName) {
                return $this->jsonResponse($response->withStatus(400), [
                    'success' => false,
                    'error' => 'Stack name and service name are required'
                ]);
            }

            $queryParams = $request->getQueryParams();
            $options = [
                'tail' => isset($queryParams['tail']) ? (int)$queryParams['tail'] : 100,
                'timestamps' => isset($queryParams['timestamps']) ? (bool)$queryParams['timestamps'] : true,
                'stdout' => isset($queryParams['stdout']) ? (bool)$queryParams['stdout'] : true,
                'stderr' => isset($queryParams['stderr']) ? (bool)$queryParams['stderr'] : true,
            ];

            $logs = $this->dockerService->getStackServiceLogs($stackName, $serviceName, $options);

            // Format logs for display
            $formattedLogs = $this->formatServiceLogs($logs);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $formattedLogs
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting stack service logs', [
                'stack' => $params['stack'] ?? 'unknown',
                'service' => $params['service'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'success' => false,
                'error' => 'Failed to get stack service logs: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Format service logs
     *
     * @param string $logs
     * @return array
     */
    private function formatServiceLogs(string $logs): array
    {
        $lines = explode("\n", $logs);
        $formattedLogs = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            // Extract timestamp if available
            if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z) (.*)$/', $line, $matches)) {
                $timestamp = $matches[1];
                $message = $matches[2];

                $formattedLogs[] = [
                    'timestamp' => $timestamp,
                    'message' => $message
                ];
            } else {
                $formattedLogs[] = [
                    'timestamp' => null,
                    'message' => $line
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