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
class LoggingServiceProvider extends ServiceProvider
{
    /**
     * Services that should be registered as singletons
     *
     * @var array
     */
    protected array $singletons = [
        LogManager::class => null
    ];

    /**
     * Services that should be registered as aliases
     *
     * @var array
     */
    protected array $aliases = [
        'log' => LogManager::class,
        'logger' => LoggerInterface::class
    ];

    /**
     * Register custom services
     *
     * @return void
     */
    public function register(): void
    {

    }

    /**
     * Bootstrap any application services
     *
     * @return void
     */
    public function boot(): void
    {
        // No bootstrapping needed
        // Register a default NullLogger for LoggerInterface to prevent circular dependencies
        $this->instance(LoggerInterface::class, new NullLogger());

        // Register LogManager
        $this->singleton(LogManager::class, function ($container) {
            /** @var Config $config */
            $config = $container->make(Config::class);

            // Get log configuration
            $loggingConfig = $config->get('logging', []);

            return new LogManager($loggingConfig);
        });

        // Now update LoggerInterface binding to use the actual logger from LogManager
        $this->singleton(LoggerInterface::class, function ($container) {
            return $container->make(LogManager::class)->channel();
        });
    }
}