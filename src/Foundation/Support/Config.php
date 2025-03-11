<?php
namespace Ody\Core\Foundation\Support;

/**
 * Configuration repository
 */
class Config
{
    /**
     * All the configuration items.
     *
     * @var array
     */
    protected array $items = [];

    /**
     * Create a new configuration repository.
     *
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Determine if the given configuration value exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get the specified configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (is_null($key)) {
            return $this->items;
        }

        if (isset($this->items[$key])) {
            return $this->items[$key];
        }

        $segments = explode('.', $key);
        $items = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($items) || !array_key_exists($segment, $items)) {
                return $default;
            }

            $items = $items[$segment];
        }

        return $items;
    }

    /**
     * Set a given configuration value.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $items = &$this->items;

        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($items[$key]) || !is_array($items[$key])) {
                $items[$key] = [];
            }
            $items = &$items[$key];
        }

        $items[array_shift($keys)] = $value;
    }

    /**
     * Load configuration files from a directory.
     *
     * @param string $path
     * @return void
     */
    public function loadFromDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = scandir($path);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (is_file($path . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $this->items[$name] = require $path . '/' . $file;
            }
        }
    }

    /**
     * Get all of the configuration items.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Merge configuration items with the existing ones.
     *
     * @param array $items
     * @return void
     */
    public function merge(array $items): void
    {
        $this->items = array_merge($this->items, $items);
    }
}