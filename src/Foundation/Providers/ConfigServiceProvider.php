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
        // Register Config service
        $this->container->singleton(Config::class, function ($container) {
            $config = new Config();

            // Get the config path from Env or use default
            $configPath = Env::get('CONFIG_PATH', $this->getDefaultConfigPath());

            // Load configuration files from directory
            if (is_dir($configPath)) {
                $config->loadFromDirectory($configPath);
            }

            // Merge with any existing configuration
            if ($this->container->bound('config') && is_array($this->container->make('config'))) {
                $config->merge($this->container->make('config'));
            }

            return $config;
        });

        // Register config alias
        $this->container->alias(Config::class, 'config');
    }

    /**
     * Get the default config path
     *
     * @return string
     */
    protected function getDefaultConfigPath(): string
    {
        return dirname(__DIR__, 4) . '/config';
    }

    /**
     * Bootstrap any application services
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        // No bootstrapping needed
    }
}