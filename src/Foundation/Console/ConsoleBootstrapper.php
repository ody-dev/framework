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

        // Get or create service provider manager
        $providerManager = $container->has(ServiceProviderManager::class)
            ? $container->make(ServiceProviderManager::class)
            : new ServiceProviderManager($container);

        $container->instance(ServiceProviderManager::class, $providerManager);

        // Register core providers
        self::registerCoreProviders($providerManager);

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
    }

    /**
     * Register core service providers
     *
     * @param ServiceProviderManager $providerManager
     * @return void
     */
    protected static function registerCoreProviders(ServiceProviderManager $providerManager): void
    {
        // Core providers that must be registered in console environment
        $coreProviders = [
            \Ody\Foundation\Providers\ConfigServiceProvider::class,
            \Ody\Foundation\Providers\LoggingServiceProvider::class,
            \Ody\Foundation\Providers\ConsoleServiceProvider::class,
        ];

        // Register each core provider
        foreach ($coreProviders as $provider) {
            if (class_exists($provider) && !$providerManager->isRegistered($provider)) {
                $providerManager->register($provider);
            }
        }

        // Try to load additional providers from config if available
        if ($providerManager->getContainer()->has(Config::class)) {
            $config = $providerManager->getContainer()->make(Config::class);
            if ($config) {
                $configProviders = $config->get('app.providers', []);

                foreach ($configProviders as $provider) {
                    if (class_exists($provider) && !$providerManager->isRegistered($provider)) {
                        $providerManager->register($provider);
                    }
                }
            }
        }

        // Boot all registered providers
        $providerManager->boot();
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