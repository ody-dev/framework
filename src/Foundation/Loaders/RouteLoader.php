<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Core\Foundation\Loaders;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Middleware\Middleware;
use Ody\Core\Foundation\Router;

/**
 * Route loader for loading routes from separate files
 */
class RouteLoader
{
    /**
     * @var Router
     */
    private $router;

    /**
     * @var Middleware
     */
    private $middleware;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var array
     */
    private $loadedFiles = [];

    /**
     * RouteLoader constructor
     *
     * @param Router $router
     * @param Middleware $middleware
     * @param Container $container
     */
    public function __construct(Router $router, Middleware $middleware, Container $container)
    {
        $this->router = $router;
        $this->middleware = $middleware;
        $this->container = $container;
    }

    /**
     * Load routes from a file
     *
     * @param string $filePath Path to route file
     * @return bool True if file was loaded, false if already loaded or not found
     */
    public function load(string $filePath): bool
    {
        // Normalize file path
        $filePath = realpath($filePath);

        if (!$filePath || !file_exists($filePath)) {
            return false;
        }

        // Don't load the same file twice
        if (in_array($filePath, $this->loadedFiles)) {
            return false;
        }

        // Add to loaded files list
        $this->loadedFiles[] = $filePath;

        // Load the file with router and middleware in scope
        $router = $this->router;
        $middleware = $this->middleware;
        $container = $this->container;

        require $filePath;

        return true;
    }

    /**
     * Load all route files from a directory
     *
     * @param string $directory Directory containing route files
     * @param string $extension File extension to look for (default: .php)
     * @return int Number of files loaded
     */
    public function loadDirectory(string $directory, string $extension = '.php'): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $count = 0;
        $files = scandir($directory);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $directory . '/' . $file;

            if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === ltrim($extension, '.')) {
                if ($this->load($filePath)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Get list of loaded files
     *
     * @return array
     */
    public function getLoadedFiles(): array
    {
        return $this->loadedFiles;
    }
}