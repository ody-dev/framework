<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace App\Services;

use GuzzleHttp\Client;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Docker Stack Service
 *
 * Add these methods to your existing DockerService class
 */
class DockerStackService
{
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    /**
     * List all stacks
     *
     * @return array
     */
    public function listStacks()
    {
        try {
            // Docker API doesn't have direct stack support, so we use the command line
            $process = new Process(['docker', 'stack', 'ls', '--format', '{{json .}}']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = trim($process->getOutput());
            if (empty($output)) {
                return [];
            }

            // Parse the JSON lines
            $stacks = [];
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                $stack = json_decode($line, true);
                if ($stack) {
                    // Add additional status information if available
                    $stack['Status'] = $this->getStackStatus($stack['Name']);
                    $stacks[] = $stack;
                }
            }

            return $stacks;
        } catch (\Exception $e) {
            $this->logger->error('Error listing stacks', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get stack status
     *
     * @param string $stackName
     * @return string
     */
    public function getStackStatus($stackName)
    {
        try {
            // Check services status to determine stack status
            $services = $this->getStackServices($stackName);

            if (empty($services)) {
                return 'Unknown';
            }

            $statuses = array_column($services, 'Status');

            // If any service is in a non-running state, the stack is in that state
            if (in_array('Failed', $statuses)) {
                return 'Failed';
            }

            if (in_array('Pending', $statuses)) {
                return 'Pending';
            }

            if (in_array('Deploying', $statuses)) {
                return 'Deploying';
            }

            if (in_array('Updating', $statuses)) {
                return 'Updating';
            }

            // Check if all services are running their expected replicas
            $partial = false;
            foreach ($services as $service) {
                if ($service['RunningReplicas'] != $service['Replicas']) {
                    $partial = true;
                    break;
                }
            }

            if ($partial) {
                return 'Partial';
            }

            return 'Active';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get stack details
     *
     * @param string $stackName
     * @return array
     */
    public function getStack($stackName)
    {
        try {
            // Check if stack exists
            $stacks = $this->listStacks();
            $stack = null;

            foreach ($stacks as $s) {
                if ($s['Name'] === $stackName) {
                    $stack = $s;
                    break;
                }
            }

            if (!$stack) {
                throw new \Exception("Stack {$stackName} not found");
            }

            // Get services in the stack
            $services = $this->getStackServices($stackName);

            // Add services to the stack details
            $stack['Services'] = $services;
            $stack['Status'] = $this->getStackStatus($stackName);

            return $stack;
        } catch (\Exception $e) {
            $this->logger->error('Error getting stack details', [
                'stack' => $stackName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get services in a stack
     *
     * @param string $stackName
     * @return array
     */
    public function getStackServices($stackName)
    {
        try {
            $process = new Process(['docker', 'stack', 'services', $stackName, '--format', '{{json .}}']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = trim($process->getOutput());
            if (empty($output)) {
                return [];
            }

            // Parse the JSON lines
            $services = [];
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                $service = json_decode($line, true);
                if ($service) {
                    // Extract replicas from the mode field (e.g., "replicated 3/3")
                    if (preg_match('/replicated (\d+)\/(\d+)/', $service['Mode'], $matches)) {
                        $service['RunningReplicas'] = (int)$matches[1];
                        $service['Replicas'] = (int)$matches[2];
                    } else {
                        $service['RunningReplicas'] = 0;
                        $service['Replicas'] = 0;
                    }

                    // Get service ports
                    $service['Ports'] = $this->getServicePorts($service['Name']);

                    $services[] = $service;
                }
            }

            return $services;
        } catch (\Exception $e) {
            $this->logger->error('Error getting stack services', [
                'stack' => $stackName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get ports for a service
     *
     * @param string $serviceName
     * @return array
     */
    public function getServicePorts($serviceName)
    {
        try {
            $process = new Process(['docker', 'service', 'inspect', $serviceName, '--format', '{{json .Endpoint.Ports}}']);
            $process->run();

            if (!$process->isSuccessful()) {
                return [];
            }

            $output = trim($process->getOutput());
            if (empty($output) || $output === 'null') {
                return [];
            }

            $ports = json_decode($output, true);
            if (!$ports) {
                return [];
            }

            $formattedPorts = [];
            foreach ($ports as $port) {
                if (isset($port['PublishedPort']) && isset($port['TargetPort'])) {
                    $formattedPorts[] = "{$port['PublishedPort']}:{$port['TargetPort']}/{$port['Protocol']}";
                } elseif (isset($port['TargetPort'])) {
                    $formattedPorts[] = "{$port['TargetPort']}/{$port['Protocol']}";
                }
            }

            return $formattedPorts;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get stack compose file
     *
     * @param string $stackName
     * @return string
     */
    public function getStackComposeFile($stackName)
    {
        try {
            // Docker doesn't store the original compose file, so we need to export the current config
            $process = new Process(['docker', 'stack', 'config', $stackName]);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return $process->getOutput();
        } catch (\Exception $e) {
            $this->logger->error('Error getting stack compose file', [
                'stack' => $stackName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Deploy a stack
     *
     * @param string $stackName
     * @param string $composeContent
     * @param array $envVars
     * @param array $options
     * @return array
     */
    public function deployStack($stackName, $composeContent, $envVars = [], $options = [])
    {
        try {
            // Create a temporary file for the compose content
            $tempFile = tempnam(sys_get_temp_dir(), 'docker-compose-');
            file_put_contents($tempFile, $composeContent);

            // Build the command
            $command = ['docker', 'stack', 'deploy'];

            // Always use Docker Swarm as the orchestrator
            $command[] = '--orchestrator';
            $command[] = 'swarm';

            // Add resolve image option if specified
            if (isset($options['resolveImage'])) {
                $command[] = '--resolve-image';
                $command[] = $options['resolveImage'];
            }

            // Add prune option if specified
            if (isset($options['prune']) && $options['prune']) {
                $command[] = '--prune';
            }

            // Add compose file flag
            $command[] = '--compose-file';
            $command[] = $tempFile;

            // Add stack name
            $command[] = $stackName;

            // Prepare environment variables for the process
            $env = $_ENV;
            foreach ($envVars as $key => $value) {
                $env[$key] = $value;
            }

            // Run the command
            $process = new Process($command, null, $env);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            // Clean up the temp file
            unlink($tempFile);

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Get the newly deployed stack
            $stack = $this->getStack($stackName);

            return $stack;
        } catch (\Exception $e) {
            $this->logger->error('Error deploying stack', [
                'stack' => $stackName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update a stack
     *
     * @param string $stackName
     * @param string $composeContent
     * @param array $envVars
     * @param array $options
     * @return array
     */
    public function updateStack($stackName, $composeContent, $envVars = [], $options = [])
    {
        // For Docker Swarm, updating a stack is the same as deploying it
        return $this->deployStack($stackName, $composeContent, $envVars, $options);
    }

    /**
     * Remove a stack
     *
     * @param string $stackName
     * @return bool
     */
    public function removeStack($stackName)
    {
        try {
            // Always use Docker Swarm as the orchestrator
            $orchestrator = 'swarm';

            $process = new Process([
                'docker',
                'stack',
                'rm',
                '--orchestrator',
                $orchestrator,
                $stackName
            ]);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error removing stack', [
                'stack' => $stackName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get stack service logs
     *
     * @param string $stackName
     * @param string $serviceName
     * @param array $options
     * @return string
     */
    public function getStackServiceLogs($stackName, $serviceName, $options = [])
    {
        try {
            // Build the command
            $command = ['docker', 'service', 'logs'];

            // Add options
            if (isset($options['tail'])) {
                $command[] = '--tail';
                $command[] = (string)$options['tail'];
            }

            if (isset($options['timestamps']) && $options['timestamps']) {
                $command[] = '--timestamps';
            }

            if (isset($options['follow']) && $options['follow']) {
                $command[] = '--follow';
            }

            // Add service name - prefix with stack name if not already included
            if (strpos($serviceName, $stackName.'_') !== 0) {
                $serviceName = $stackName.'_'.$serviceName;
            }

            $command[] = $serviceName;

            $process = new Process($command);
            $process->setTimeout(60); // 1 minute timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return $process->getOutput();
        } catch (\Exception $e) {
            $this->logger->error('Error getting stack service logs', [
                'stack' => $stackName,
                'service' => $serviceName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}