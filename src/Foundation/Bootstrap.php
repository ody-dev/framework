<?php
declare(strict_types=1);

namespace Ody\Foundation;

use Nyholm\Psr7\Factory\Psr17Factory;
use Ody\Container\Container;
use Ody\Foundation\Providers\ServiceProviderManager;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Application Bootstrap
 *
 * Central bootstrap point for both web and console entry points.
 */
class Bootstrap
{
    /**
     * Core providers that must be registered in proper order
     *
     * @var array|string[]
     */
    private static array $coreProviders = [
        \Ody\Foundation\Providers\EnvServiceProvider::class,
        \Ody\Foundation\Providers\ConfigServiceProvider::class,
        \Ody\Foundation\Providers\LoggingServiceProvider::class,
        \Ody\Foundation\Providers\ApplicationServiceProvider::class,
        \Ody\Foundation\Providers\FacadeServiceProvider::class,
        \Ody\Foundation\Providers\MiddlewareServiceProvider::class,
        \Ody\Foundation\Providers\RouteServiceProvider::class,
    ];

    /**
     * Initialize the application
     *
     * @param Container|null $container
     * @param string|null $basePath
     * @param string|null $environment
     * @return Application
     */
    public static function init(?Container $container = null, ?string $basePath = null, ?string $environment = null): Application
    {
        // 1. Initialize base path
        $basePath = self::initBasePath($basePath);

        // 2. Setup container
        $container = self::initContainer($container);

        // 3. Register a minimal logger for bootstrap phase
        $container->singleton(LoggerInterface::class, function() {
            return new NullLogger();
        });

        // 4. Create provider manager
        $providerManager = new ServiceProviderManager($container);
        $container->instance(ServiceProviderManager::class, $providerManager);

        // 5. Register core providers
        self::registerCoreProviders($providerManager);

        // 6. Create and bootstrap the application
        $application = self::createApplication($container, $providerManager);

        return $application;
    }

    /**
     * Initialize the base path
     *
     * @param string|null $basePath
     * @return string
     */
    private static function initBasePath(?string $basePath = null): string
    {
        // Use provided path, or determine from current file location
        $basePath = $basePath ?? dirname(__DIR__, 2);

        // Define constant for global access if not already defined
        if (!defined('APP_BASE_PATH')) {
            define('APP_BASE_PATH', $basePath);
        }

        return $basePath;
    }

    /**
     * Initialize the container
     *
     * @param Container|null $container
     * @return Container
     */
    private static function initContainer(?Container $container = null): Container
    {
        // Create container if not provided
        $container = $container ?? new Container();

        // Set as global instance
        Container::setInstance($container);

        return $container;
    }

    /**
     * Register core providers
     *
     * @param ServiceProviderManager $providerManager
     * @return void
     */
    private static function registerCoreProviders(ServiceProviderManager $providerManager): void
    {
        foreach (self::$coreProviders as $provider) {
            if (class_exists($provider) && !$providerManager->isRegistered($provider)) {
                $providerManager->register($provider);
            }
        }

        // Boot all registered providers
        $providerManager->boot();
    }

    /**
     * Create and bootstrap the application
     *
     * @param Container $container
     * @param ServiceProviderManager $providerManager
     * @return Application
     */
    private static function createApplication(Container $container, ServiceProviderManager $providerManager): Application
    {
        // Create the application
        $application = $container->has(Application::class)
            ? $container->make(Application::class)
            : new Application($container, $providerManager);

        $container->instance(Application::class, $application);

        // Set environment from configuration
        if ($container->has('config')) {
            $config = $container->make('config');
            $environment = $config->get('app.env', 'production');

            // Set environment in application
            if (method_exists($application, 'setEnvironment')) {
                $application->setEnvironment($environment);
            }
        }

        return $application;
    }
}