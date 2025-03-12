<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Core\Foundation\Support;

use ArrayAccess;
use Ody\Core\Foundation\Contracts\ConfigRepository as ConfigRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Enhanced Configuration Repository
 *
 * A more efficient and feature-rich configuration system that provides:
 * - Better caching of processed values
 * - More efficient dot notation access
 * - Enhanced environment variable resolution
 * - Immutable configuration option
 * - ArrayAccess support
 * - Path normalization
 */
class ConfigRepository implements ArrayAccess, ConfigRepositoryInterface
{
    /**
     * Configuration items
     *
     * @var array
     */
    protected array $items = [];

    /**
     * Processed configuration values cache
     *
     * @var array
     */
    protected array $processedCache = [];

    /**
     * Loaded configuration files
     *
     * @var array
     */
    protected array $loadedFiles = [];

    /**
     * Whether configuration can be modified
     *
     * @var bool
     */
    protected bool $immutable = false;

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Application base path
     *
     * @var string|null
     */
    protected ?string $basePath = null;

    /**
     * Create a new configuration repository
     *
     * @param array $items Initial configuration items
     * @param LoggerInterface|null $logger Logger for debugging and errors
     * @param bool $immutable Whether the configuration can be modified after creation
     */
    public function __construct(
        array $items = [],
        ?LoggerInterface $logger = null,
        bool $immutable = false
    ) {
        $this->items = $items;
        $this->logger = $logger ?? new NullLogger();
        $this->immutable = $immutable;

        // Set the base path
        $this->basePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : dirname(__DIR__, 3);
    }

    /**
     * Determine if the given configuration value exists
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        // Check if already processed
        if (isset($this->processedCache[$key])) {
            return true;
        }

        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }

    /**
     * Get the specified configuration value
     *
     * @param string|null $key Key in dot notation (e.g., 'app.providers')
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function get(?string $key, $default = null)
    {
        // Return all items if no key provided
        if ($key === null) {
            return $this->items;
        }

        // Check for cached processed value
        if (array_key_exists($key, $this->processedCache)) {
            return $this->processedCache[$key];
        }

        // Direct key access (no dot notation)
        if (array_key_exists($key, $this->items)) {
            $value = $this->processValue($this->items[$key]);
            $this->processedCache[$key] = $value;
            return $value;
        }

        // Dot notation access
        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        // Process and cache the value
        $value = $this->processValue($current);
        $this->processedCache[$key] = $value;

        return $value;
    }

    /**
     * Set a given configuration value
     *
     * @param string $key Key in dot notation
     * @param mixed $value Value to set
     * @return void
     * @throws \RuntimeException If configuration is immutable
     */
    public function set(string $key, $value): void
    {
        if ($this->immutable) {
            throw new \RuntimeException('Cannot modify immutable configuration');
        }

        // Clear any cached processed value for this key
        unset($this->processedCache[$key]);

        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        $current = &$this->items;

        // Navigate to the correct part of the array
        foreach ($keys as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        // Set the value
        $current[$lastKey] = $value;
    }

    /**
     * Process a configuration value, resolving env variables and path placeholders
     *
     * @param mixed $value
     * @return mixed
     */
    protected function processValue($value)
    {
        // Process arrays recursively
        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->processValue($v);
            }
            return $result;
        }

        // Process strings for special placeholders
        if (is_string($value)) {
            // Process environment variables
            if (preg_match('/^env\(([^,\)]+)(?:,\s*([^)]+))?\)$/', $value, $matches)) {
                $envKey = trim($matches[1], '\'"');
                $envDefault = isset($matches[2]) ? $this->evaluateValue(trim($matches[2], '\'"')) : null;

                return Env::get($envKey, $envDefault);
            }

            // Process path references
            if (preg_match('/^path\(([^)]+)\)$/', $value, $matches)) {
                $pathType = trim($matches[1], '\'"');
                return $this->resolvePath($pathType);
            }

            // Process complex string interpolation
            if (strpos($value, '${') !== false) {
                return preg_replace_callback('/\${([^}]+)}/', function($matches) {
                    $placeholder = $matches[1];

                    // Environment variable
                    if (strpos($placeholder, 'env:') === 0) {
                        $envKey = substr($placeholder, 4);
                        return Env::get($envKey, '');
                    }

                    // Config value
                    if (strpos($placeholder, 'config:') === 0) {
                        $configKey = substr($placeholder, 7);
                        return $this->get($configKey, '');
                    }

                    // Path
                    if (strpos($placeholder, 'path:') === 0) {
                        $pathType = substr($placeholder, 5);
                        return $this->resolvePath($pathType);
                    }

                    return $matches[0]; // Return original if not recognized
                }, $value);
            }
        }

        return $value;
    }

    /**
     * Evaluate a string value for complex types
     *
     * @param string $value
     * @return mixed
     */
    protected function evaluateValue(string $value)
    {
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
            default:
                return $value;
        }
    }

    /**
     * Resolve a path based on type
     *
     * @param string $type
     * @return string
     */
    protected function resolvePath(string $type): string
    {
        switch ($type) {
            case 'base':
                return $this->basePath;
            case 'config':
                return $this->basePath . '/config';
            case 'storage':
                return $this->basePath . '/storage';
            case 'logs':
                return $this->basePath . '/storage/logs';
            case 'cache':
                return $this->basePath . '/storage/cache';
            case 'routes':
                return $this->basePath . '/routes';
            case 'app':
                return $this->basePath . '/app';
            case 'public':
                return $this->basePath . '/public';
            case 'resources':
                return $this->basePath . '/resources';
            default:
                if (strpos($type, '/') === 0) {
                    // Absolute path
                    return $type;
                }

                // Relative to base path
                return $this->basePath . '/' . $type;
        }
    }

    /**
     * Load configuration files from a directory
     *
     * @param string $path
     * @param bool $recursive Whether to load files from subdirectories
     * @return self
     */
    public function loadFromDirectory(string $path, bool $recursive = false): self
    {
        // Normalize and check path
        $path = $this->normalizePath($path);

        if (!is_dir($path) || !is_readable($path)) {
            $this->logger->warning("Config directory not found or not readable: $path");
            return $this;
        }

        $this->logger->debug("Loading configuration from directory: $path");

        // Get all PHP files from the directory
        $iterator = $recursive
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path))
            : new \DirectoryIterator($path);

        $loaded = 0;

        foreach ($iterator as $file) {
            // Skip directories and non-PHP files
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            $fileName = $file->getBasename('.php');

            // In recursive mode, respect directory structure in config keys
            if ($recursive && $iterator instanceof \RecursiveIteratorIterator) {
                $relativePath = substr(
                    $filePath,
                    strlen($path) + 1,
                    -strlen('/' . $file->getBasename())
                );

                $fileName = str_replace('/', '.', $relativePath) .
                    ($relativePath ? '.' : '') .
                    $fileName;
            }

            $this->loadFile($fileName, $filePath);
            $loaded++;
        }

        $this->logger->debug("Loaded $loaded configuration files from $path");

        return $this;
    }

    /**
     * Load a single configuration file
     *
     * @param string $name Configuration name (filename without extension)
     * @param string $path Full path to the file
     * @return self
     */
    public function loadFile(string $name, string $path): self
    {
        // Normalize path
        $path = $this->normalizePath($path);

        // Skip if already loaded
        if (isset($this->loadedFiles[$path])) {
            return $this;
        }

        // Mark as loaded (before requiring to prevent recursion)
        $this->loadedFiles[$path] = true;

        try {
            // Load the file
            $config = require $path;

            // Store if it returned an array
            if (is_array($config)) {
                $this->items[$name] = $config;
                $this->logger->debug("Loaded configuration file: $name from $path");

                // Clear any cached processed values related to this config section
                foreach (array_keys($this->processedCache) as $key) {
                    if ($key === $name || strpos($key, $name . '.') === 0) {
                        unset($this->processedCache[$key]);
                    }
                }
            } else {
                $this->logger->warning("Configuration file did not return an array: $path");
            }
        } catch (\Throwable $e) {
            $this->logger->error("Error loading configuration file: $path", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return $this;
    }

    /**
     * Get all configuration items
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Merge new items with the existing configuration
     *
     * @param array $items
     * @return self
     * @throws \RuntimeException If configuration is immutable
     */
    public function merge(array $items): self
    {
        if ($this->immutable) {
            throw new \RuntimeException('Cannot modify immutable configuration');
        }

        $this->items = array_merge_recursive($this->items, $items);

        // Clear all processed values cache since the base configuration changed
        $this->processedCache = [];

        return $this;
    }

    /**
     * Merge configuration items for a specific key
     *
     * @param string $key
     * @param array $items
     * @return self
     * @throws \RuntimeException If configuration is immutable
     */
    public function mergeKey(string $key, array $items): self
    {
        if ($this->immutable) {
            throw new \RuntimeException('Cannot modify immutable configuration');
        }

        $current = $this->get($key, []);

        if (is_array($current)) {
            $this->set($key, array_merge_recursive($current, $items));
        } else {
            $this->set($key, $items);
        }

        return $this;
    }

    /**
     * Create an immutable copy of the current configuration
     *
     * @return ConfigRepository
     */
    public function immutable(): ConfigRepository
    {
        return new self($this->items, $this->logger, true);
    }

    /**
     * Set the application base path
     *
     * @param string $path
     * @return self
     */
    public function setBasePath(string $path): self
    {
        $this->basePath = rtrim($path, '/');

        // Clear path-related cache entries
        foreach (array_keys($this->processedCache) as $key) {
            $value = $this->items[$key] ?? null;
            if (is_string($value) && (strpos($value, 'path(') === 0 || strpos($value, '${path:') !== false)) {
                unset($this->processedCache[$key]);
            }
        }

        return $this;
    }

    /**
     * Get the application base path
     *
     * @return string|null
     */
    public function getBasePath(): ?string
    {
        return $this->basePath;
    }

    /**
     * Normalize a path (convert relative paths, resolve symlinks)
     *
     * @param string $path
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        // If path is relative and base path is set, make it absolute
        if ($this->basePath && $path[0] !== '/' && $path[0] !== '\\' && !preg_match('/^[A-Z]:/i', $path)) {
            $path = $this->basePath . '/' . $path;
        }

        // Resolve real path (handles .. and symlinks)
        $realPath = realpath($path);

        return $realPath ?: $path;
    }

    /**
     * Convert the configuration to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        // Process all values
        $result = [];

        foreach ($this->items as $key => $value) {
            $result[$key] = $this->processValue($value);
        }

        return $result;
    }

    /**
     * Reset the processed values cache
     *
     * @return self
     */
    public function clearCache(): self
    {
        $this->processedCache = [];
        return $this;
    }

    /**
     * Check if offset exists (ArrayAccess interface)
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Get offset value (ArrayAccess interface)
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Set offset value (ArrayAccess interface)
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * Unset offset (ArrayAccess interface)
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        if ($this->immutable) {
            throw new \RuntimeException('Cannot modify immutable configuration');
        }

        $this->set($offset, null);
    }
}