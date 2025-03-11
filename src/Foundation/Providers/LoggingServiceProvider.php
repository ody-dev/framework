<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Support\Config;
use Ody\Core\Foundation\Logging\LogManager;
use Psr\Log\LoggerInterface;

/**
 * Service provider for logging
 */
class LoggingServiceProvider extends AbstractServiceProvider
{
    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Register LogManager
        $this->container->singleton(LogManager::class, function ($container) {
            /** @var Config $config */
            $config = $container->make(Config::class);

            // Get log configuration
            $loggingConfig = $config->get('logging', []);

            return new LogManager($loggingConfig);
        });

        // Register default logger as LoggerInterface
        $this->container->singleton(LoggerInterface::class, function ($container) {
            return $container->make(LogManager::class)->channel();
        });

        // Alias for backward compatibility
        $this->container->alias(LogManager::class, 'log');
        $this->container->alias(LoggerInterface::class, 'logger');
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