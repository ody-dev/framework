<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Support\Config;
use Ody\Core\Foundation\Support\Env;

/**
 * Service provider for configuration
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
        // Ensure Config class is registered
        if (!$this->container->bound(Config::class)) {
            $config = new Config();

            // Load config files from default location
            $configPath = env('CONFIG_PATH', base_path('config'));
            if (is_dir($configPath)) {
                $config->loadFromDirectory($configPath);
            }

            $this->container->instance('config', $config);
            $this->container->instance(Config::class, $config);
        }
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
             * @return mixed|Config
             */
            function config($key = null, $default = null) {
                $config = app(Config::class);

                if (is_null($key)) {
                    return $config;
                }

                return $config->get($key, $default);
            }
        }
    }
}