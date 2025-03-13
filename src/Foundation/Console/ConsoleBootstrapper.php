<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Console;

use Ody\Container\Container;
use Ody\Foundation\Application;
use Ody\Foundation\Providers\ServiceProviderManager;
use Ody\Foundation\Support\Config;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Console Bootstrapper
 *
 * This class helps initialize the console environment with proper dependencies
 */
class ConsoleBootstrapper
{
    /**
     * Bootstrap the console environment
     *
     * @param Container|null $container
     * @return Container
     */
    public static function bootstrap(?Container $container = null): Container
    {
        // Initialize container if not provided
        $container = $container ?: new Container();
        Container::setInstance($container);

        // Register essential services
        self::registerEssentialServices($container);

        return $container;
    }

    /**
     * Register essential services in the container
     *
     * @param Container $container
     * @return void
     */
    protected static function registerEssentialServices(Container $container): void
    {
        // Register logger if not already registered
        if (!$container->has(LoggerInterface::class)) {
            $container->instance(LoggerInterface::class, new NullLogger());
        }

        // Register command registry
        if (!$container->has(CommandRegistry::class)) {
            $container->singleton(CommandRegistry::class, function($container) {
                return new CommandRegistry(
                    $container,
                    $container->make(LoggerInterface::class)
                );
            });
        }

        // Register ServiceProviderManager if needed
        if (!$container->has(ServiceProviderManager::class)) {
            $container->singleton(ServiceProviderManager::class, function($container) {
                $config = $container->has(Config::class) ? $container->make(Config::class) : null;
                return new ServiceProviderManager(
                    $container,
                    $config,
                    $container->make(LoggerInterface::class)
                );
            });
        }

        // Register Application if needed
        if (!$container->has(Application::class)) {
            $container->singleton(Application::class, function($container) {
                return new Application(
                    $container,
                    $container->make(ServiceProviderManager::class)
                );
            });
        }
    }

    /**
     * Initialize a configured console kernel
     *
     * @param Container|null $container
     * @return ConsoleKernel
     */
    public static function kernel(?Container $container = null): ConsoleKernel
    {
        $container = self::bootstrap($container);
        return new ConsoleKernel($container);
    }
}