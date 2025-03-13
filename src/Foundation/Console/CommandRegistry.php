<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Console;

use Ody\Container\Container;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;

/**
 * CommandRegistry
 *
 * Registry for managing console commands
 */
class CommandRegistry
{
    /**
     * @var Container
     */
    protected Container $container;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var Command[]
     */
    protected array $commands = [];

    /**
     * @var string[]
     */
    protected array $commandClasses = [];

    /**
     * CommandRegistry constructor
     *
     * @param Container $container
     * @param LoggerInterface $logger
     */
    public function __construct(Container $container, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * Add a command to the registry
     *
     * @param Command|string $command Command instance or class name
     * @return self
     */
    public function add($command): self
    {
        // If it's a command class name, store it for lazy instantiation
        if (is_string($command)) {
            if (!class_exists($command)) {
                $this->logger->warning("Command class {$command} does not exist");
                return $this;
            }

            $this->commandClasses[] = $command;
            return $this;
        }

        // If it's already a Command instance, store it directly
        if ($command instanceof Command) {
            $this->commands[$command->getName()] = $command;
            return $this;
        }

        $this->logger->warning("Invalid command type: " . gettype($command));
        return $this;
    }

    /**
     * Add multiple commands to the registry
     *
     * @param array $commands
     * @return self
     */
    public function addMultiple(array $commands): self
    {
        foreach ($commands as $command) {
            $this->add($command);
        }

        return $this;
    }

    /**
     * Get all registered commands
     *
     * @return Command[]
     */
    public function getCommands(): array
    {
        // Instantiate any lazy-loaded command classes
        $this->instantiateCommandClasses();

        return $this->commands;
    }

    /**
     * Check if a command exists in the registry
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        // Instantiate any lazy-loaded command classes
        $this->instantiateCommandClasses();

        return isset($this->commands[$name]);
    }

    /**
     * Get a specific command by name
     *
     * @param string $name
     * @return Command|null
     */
    public function get(string $name): ?Command
    {
        // Instantiate any lazy-loaded command classes
        $this->instantiateCommandClasses();

        return $this->commands[$name] ?? null;
    }

    /**
     * Clear the command registry
     *
     * @return self
     */
    public function clear(): self
    {
        $this->commands = [];
        $this->commandClasses = [];

        return $this;
    }

    /**
     * Instantiate all registered command classes
     *
     * @return void
     */
    protected function instantiateCommandClasses(): void
    {
        if (empty($this->commandClasses)) {
            return;
        }

        $commandClassesToProcess = $this->commandClasses;
        $this->commandClasses = []; // Clear the list to avoid processing them again

        foreach ($commandClassesToProcess as $class) {
            try {
                // Skip if the class doesn't exist
                if (!class_exists($class)) {
                    $this->logger->warning("Command class {$class} does not exist");
                    continue;
                }

                // Create the command using the container if possible
                if ($this->container->has($class)) {
                    $command = $this->container->make($class);
                } else {
                    $command = new $class();
                }

                if ($command instanceof \Symfony\Component\Console\Command\Command) {
                    $this->commands[$command->getName()] = $command;
                } else {
                    $this->logger->warning("Class {$class} is not a Symfony Command");
                }
            } catch (\Throwable $e) {
                $this->logger->error("Error instantiating command {$class}: " . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }
}