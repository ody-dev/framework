<?php
/*
 * This file is part of ODY framework
 *
 * @link https://ody.dev
 * @documentation https://ody.dev/docs
 * @license https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace App\Services;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Docker API Service
 *
 * Communicates with the Docker Engine API
 */
class DockerService
{
    /**
     * @var Client HTTP client
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string Docker API host
     */
    private $host;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->host = env('DOCKER_HOST', 'unix:///var/run/docker.sock');

        // Create the HTTP client with the appropriate configuration
        if (strpos($this->host, 'unix://') === 0) {
            // Unix socket
            $socketPath = str_replace('unix://', '', $this->host);
            $this->client = new Client([
                'curl' => [
                    CURLOPT_UNIX_SOCKET_PATH => $socketPath
                ],
                'base_uri' => 'http://localhost/v1.41/'
            ]);
        } else {
            // TCP connection
            $this->client = new Client([
                'base_uri' => $this->host . '/v1.41/'
            ]);
        }
    }

    /**
     * Get information about the Docker daemon
     *
     * @return array
     */
    public function getInfo()
    {
        try {
            $response = $this->client->get('info');
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->logger->error('Error getting Docker info', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * List all containers
     *
     * @param bool $all Include stopped containers
     * @return array
     */
    public function listContainers($all = true)
    {
        try {
            $response = $this->client->get('containers/json', [
                'query' => ['all' => $all ? 'true' : 'false']
            ]);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->logger->error('Error listing containers', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get detailed information about a container
     *
     * @param string $containerId
     * @return array
     */
    public function inspectContainer($containerId)
    {
        try {
            $response = $this->client->get("containers/{$containerId}/json");
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->logger->error('Error inspecting container', [
                'containerId' => $containerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get container logs
     *
     * @param string $containerId
     * @param array $options
     * @return string
     */
    public function getContainerLogs($containerId, array $options = [])
    {
        // Default options
        $defaultOptions = [
            'stdout' => true,
            'stderr' => true,
            'tail' => 100,
            'timestamps' => true
        ];

        $queryParams = array_merge($defaultOptions, $options);

        try {
            $response = $this->client->get("containers/{$containerId}/logs", [
                'query' => $queryParams
            ]);
            return (string) $response->getBody();
        } catch (\Exception $e) {
            $this->logger->error('Error getting container logs', [
                'containerId' => $containerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Start a container
     *
     * @param string $containerId
     * @return bool
     */
    public function startContainer($containerId)
    {
        try {
            $this->client->post("containers/{$containerId}/start");
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error starting container', [
                'containerId' => $containerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Stop a container
     *
     * @param string $containerId
     * @param int $timeout Seconds to wait before killing the container
     * @return bool
     */
    public function stopContainer($containerId, $timeout = 10)
    {
        try {
            $this->client->post("containers/{$containerId}/stop", [
                'query' => ['t' => $timeout]
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error stopping container', [
                'containerId' => $containerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Restart a container
     *
     * @param string $containerId
     * @param int $timeout Seconds to wait before killing the container
     * @return bool
     */
    public function restartContainer($containerId, $timeout = 10)
    {
        try {
            $this->client->post("containers/{$containerId}/restart", [
                'query' => ['t' => $timeout]
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error restarting container', [
                'containerId' => $containerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * List all docker images
     *
     * @return array
     */
    public function listImages()
    {
        try {
            $response = $this->client->get('images/json');
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->logger->error('Error listing images', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get system-wide information about docker
     *
     * @return array
     */
    public function getSystemInfo()
    {
        try {
            $response = $this->client->get('info');
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->logger->error('Error getting system info', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get docker version information
     *
     * @return array
     */
    public function getVersion()
    {
        try {
            $response = $this->client->get('version');
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->logger->error('Error getting version', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}