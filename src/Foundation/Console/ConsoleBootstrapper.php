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
use Ody\Foundation\Providers\ServiceProviderManager;
use Ody\Foundation\Support\Config;
use Symfony\Component\Console\Application as ConsoleApplication;

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

        // Get or create service provider manager
        $providerManager = $container->has(ServiceProviderManager::class)
            ? $container->make(ServiceProviderManager::class)
            : new ServiceProviderManager($container);

        $container->instance(ServiceProviderManager::class, $providerManager);

        // Register core providers
        self::registerServiceProviders($providerManager);

        return $container;
    }

    /**
     * Register core service providers
     *
     * @param ServiceProviderManager $providerManager
     * @return void
     */
    protected static function registerServiceProviders(ServiceProviderManager $providerManager): void
    {
        // Core providers that must be registered in console environment
        $coreProviders = [
            \Ody\Foundation\Providers\EnvServiceProvider::class,
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

        $config = $providerManager->getContainer()->make(Config::class);
        if ($config) {
            $configProviders = $config->get('app.providers', []);

            foreach ($configProviders as $provider) {
                if (class_exists($provider) && !$providerManager->isRegistered($provider)) {
                    $providerManager->register($provider);
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

        // Get the kernel from the container if possible
        if ($container->has(ConsoleKernel::class)) {
            return $container->make(ConsoleKernel::class);
        }

        // If not, we need to manually create the kernel with its dependencies
        if ($container->has(ConsoleApplication::class) && $container->has(CommandRegistry::class)) {
            $console = $container->make(ConsoleApplication::class);
            $registry = $container->make(CommandRegistry::class);

            return new ConsoleKernel($container, $console, $registry);
        }

        // Fallback to simple creation, kernel will resolve its dependencies
        return new ConsoleKernel($container);
    }
}