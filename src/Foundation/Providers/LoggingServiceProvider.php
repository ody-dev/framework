<?php
namespace Ody\Foundation\Providers;

use Ody\Container\Container;
use Ody\Foundation\Support\Config;
use Ody\Foundation\Logging\LogManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service provider for logging
 */
class LoggingServiceProvider extends AbstractServiceProviderInterface
{
    /**
     * Services that should be registered as singletons
     *
     * @var array
     */
    protected $singletons = [
        LogManager::class => null
    ];

    /**
     * Services that should be registered as aliases
     *
     * @var array
     */
    protected $aliases = [
        'log' => LogManager::class,
        'logger' => LoggerInterface::class
    ];

    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Register a default NullLogger for LoggerInterface to prevent circular dependencies
        $this->instance(LoggerInterface::class, new NullLogger());

        // Register LogManager
        $this->registerSingleton(LogManager::class, function ($container) {
            /** @var Config $config */
            $config = $container->make(Config::class);

            // Get log configuration
            $loggingConfig = $config->get('logging', []);

            return new LogManager($loggingConfig);
        });

        // Now update LoggerInterface binding to use the actual logger from LogManager
        $this->registerSingleton(LoggerInterface::class, function ($container) {
            return $container->make(LogManager::class)->channel();
        });
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