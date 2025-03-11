<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Support\Config;
use Ody\Core\Foundation\Logger;

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
        // Register logger
        $this->container->singleton(Logger::class, function ($container) {
            /** @var Config $config */
            $config = $container->make(Config::class);

            // Get log configuration
            $channel = $config->get('logging.default', 'file');
            $level = $config->get('logging.level', Logger::LEVEL_INFO);
            $path = $config->get("logging.channels.{$channel}.path", 'storage/logs/api.log');

            // Ensure log directory exists
            $logDir = dirname($path);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            return new Logger($path, $level);
        });

        // Alias logger
        $this->container->alias(Logger::class, 'logger');
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