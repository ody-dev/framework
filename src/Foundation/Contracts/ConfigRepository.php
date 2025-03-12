<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Core\Foundation\Contracts;

/**
 * Configuration Repository Contract
 *
 * Defines the interface for configuration repositories.
 */
interface ConfigRepository
{
    /**
     * Determine if the given configuration value exists
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Get the specified configuration value
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function get(?string $key, $default = null);

    /**
     * Set a given configuration value
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, $value): void;

    /**
     * Load configuration files from a directory
     *
     * @param string $path
     * @param bool $recursive
     * @return self
     */
    public function loadFromDirectory(string $path, bool $recursive = false): self;

    /**
     * Load a single configuration file
     *
     * @param string $name
     * @param string $path
     * @return self
     */
    public function loadFile(string $name, string $path): self;

    /**
     * Get all configuration items
     *
     * @return array
     */
    public function all(): array;

    /**
     * Merge new items with the existing configuration
     *
     * @param array $items
     * @return self
     */
    public function merge(array $items): self;

    /**
     * Merge configuration items for a specific key
     *
     * @param string $key
     * @param array $items
     * @return self
     */
    public function mergeKey(string $key, array $items): self;

    /**
     * Create an immutable copy of the current configuration
     *
     * @return ConfigRepository
     */
    public function immutable(): ConfigRepository;

    /**
     * Set the application base path
     *
     * @param string $path
     * @return self
     */
    public function setBasePath(string $path): self;

    /**
     * Get the application base path
     *
     * @return string|null
     */
    public function getBasePath(): ?string;

    /**
     * Convert the configuration to an array
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Reset the processed values cache
     *
     * @return self
     */
    public function clearCache(): self;
}