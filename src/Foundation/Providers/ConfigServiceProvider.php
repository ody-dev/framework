<?php
namespace Ody\Foundation\Providers;

use Ody\Container\Container;
use Ody\Foundation\Support\Config;
use Ody\Foundation\Support\Env;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service provider for configuration
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register custom services
     *
     * @return void
     */
    public function register(): void
    {
        // Get or create a logger for diagnostics
        $logger = $this->container->has(LoggerInterface::class)
            ? $this->container->make(LoggerInterface::class)
            : new NullLogger();

        // Create a new config instance
        $config = new Config();

        // Load config files from possible paths
        $configLoaded = $this->loadConfigFromPossiblePaths($config, $logger);

        if (!$configLoaded) {
            $logger->warning("Failed to load any configuration files");
        }

        // Register in container
        $this->container->instance('config', $config);
        $this->container->instance(Config::class, $config);

        // Log diagnostic info
        $configCount = count($config->all());
        $logger->info("Configuration loaded with {$configCount} root keys", [
            'keys' => array_keys($config->all())
        ]);
    }

    /**
     * Load configuration from multiple possible paths
     *
     * @param Config $config
     * @param LoggerInterface $logger
     * @return bool True if configuration was loaded successfully
     */
    protected function loadConfigFromPossiblePaths(Config $config, LoggerInterface $logger): bool
    {
        // First check for environment-defined config path
        $configPath = env('CONFIG_PATH');

        if ($configPath && is_dir($configPath)) {
            $logger->info("Loading config from environment path: {$configPath}");
            $config->loadFromDirectory($configPath);
            return count($config->all()) > 0;
        }

        // List of possible config paths in order of priority
        $possiblePaths = [
            // From APP_BASE_PATH constant
            defined('APP_BASE_PATH') ? rtrim(APP_BASE_PATH, '/') . '/config' : null,

            // Relative to current directory
            getcwd() . '/config',

            // Up from src/Foundation/Providers
            dirname(__DIR__, 3) . '/config',

            // In case we're in vendor directory
            dirname(__DIR__, 5) . '/config',
        ];

        // Filter out null paths
        $possiblePaths = array_filter($possiblePaths);

        // Try each path
        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                $logger->info("Loading config from directory: {$path}");
                $config->loadFromDirectory($path);

                // If we found configs, no need to keep searching
                if (count($config->all()) > 0) {
                    return true;
                }
            } else {
                $logger->debug("Config directory not found: {$path}");
            }
        }

        // If no directory-based loading worked, try individual files
        if (count($config->all()) === 0) {
            $logger->warning("No config directory found, trying individual files");
            return $this->loadIndividualConfigFiles($config, $possiblePaths, $logger);
        }

        return count($config->all()) > 0;
    }

    /**
     * Load individual important config files
     *
     * @param Config $config
     * @param array $basePaths
     * @param LoggerInterface $logger
     * @return bool
     */
    protected function loadIndividualConfigFiles(Config $config, array $basePaths, LoggerInterface $logger): bool
    {
        // Core config files to load
        $configFiles = ['app', 'database', 'logging'];
        $loaded = false;

        foreach ($configFiles as $configName) {
            foreach ($basePaths as $path) {
                $filePath = $path . '/' . $configName . '.php';
                if (file_exists($filePath)) {
                    $logger->info("Loading config file: {$filePath}");
                    $config->loadFile($configName, $filePath);
                    $loaded = true;
                    break;
                }
            }
        }

        return $loaded;
    }

    /**
     * Bootstrap any application services
     *
     * @return void
     */
    public function boot(): void
    {
        // No bootstrapping needed for config
    }
}