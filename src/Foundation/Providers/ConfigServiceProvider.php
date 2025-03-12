<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Contracts\ConfigRepository;
use Ody\Core\Foundation\Support\ConfigRepository as ConfigImpl;
use Ody\Core\Foundation\Support\Env;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Configuration System Service Provider
 */
class ConfigServiceProvider extends AbstractServiceProvider
{
    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Get base path
        $basePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : dirname(__DIR__, 3);

        // Get logger if available, or use null logger
        $logger = $this->container->has(LoggerInterface::class)
            ? $this->container->make(LoggerInterface::class)
            : new NullLogger();

        // Create the configuration repository
        $config = new ConfigImpl([], $logger);
        $config->setBasePath($basePath);

        // Load configuration from default directory
        $configPath = env('CONFIG_PATH', $basePath . '/config');

        if (is_dir($configPath)) {
            $recursive = (bool) env('CONFIG_RECURSIVE', false);
            $config->loadFromDirectory($configPath, $recursive);
        }

        // Load environment-specific configuration if available
        $environment = env('APP_ENV', 'production');
        $envConfigPath = $configPath . '/' . $environment;

        if (is_dir($envConfigPath)) {
            $config->loadFromDirectory($envConfigPath, $recursive);
        }

        // Register in container under both class and interface
        $this->container->instance(ConfigImpl::class, $config);
        $this->container->instance(ConfigRepository::class, $config);

        // Register as 'config' alias for backward compatibility
        $this->container->instance('config', $config);
    }

    /**
     * Bootstrap any application services
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Register helper functions if not already registered
        if (!function_exists('config')) {
            $this->registerHelpers();
        }
    }

    /**
     * Register helper functions related to config
     *
     * @return void
     */
    protected function registerHelpers(): void
    {
        // This is just a safeguard in case the helpers.php file wasn't autoloaded
        if (!function_exists('config')) {
            /**
             * Get configuration value
             *
             * @param string|null $key
             * @param mixed $default
             * @return mixed|ConfigRepository
             */
            function config($key = null, $default = null) {
                $config = app(ConfigRepository::class);

                if (is_null($key)) {
                    return $config;
                }

                return $config->get($key, $default);
            }
        }
    }
}