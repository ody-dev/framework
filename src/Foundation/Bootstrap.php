<?php
declare(strict_types=1);

namespace Ody\Foundation;

use Ody\Container\Container;
use Ody\Foundation\Providers\ApplicationServiceProvider;
use Ody\Foundation\Providers\ConfigServiceProvider;
use Ody\Foundation\Providers\EnvServiceProvider;
use Ody\Foundation\Providers\FacadeServiceProvider;
use Ody\Foundation\Providers\LoggingServiceProvider;
use Ody\Foundation\Providers\MiddlewareServiceProvider;
use Ody\Foundation\Providers\RouteServiceProvider;
use Ody\Foundation\Providers\ServiceProviderManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Application Bootstrap
 *
 * Central bootstrap point for both web and console entry points.
 */
class Bootstrap
{
    /**
     * Initialize the application
     *
     * @param Container|null $container
     * @param string|null $basePath
     * @return Application
     */
    public static function init(
        ?Container $container = null,
        ?string    $basePath = null,
    ): Application
    {
        self::initBasePath($basePath);

        $container = self::initContainer($container);

        $container->singleton(LoggerInterface::class, function () {
            return new NullLogger();
        });

        $providerManager = new ServiceProviderManager($container);
        $container->instance(ServiceProviderManager::class, $providerManager);

        $application = self::createApplication($container, $providerManager);

        return $application;
    }

    /**
     * Initialize the base path
     *
     * @param string|null $basePath
     * @return void
     */
    private static function initBasePath(?string $basePath = null): void
    {
        // Use provided path, or determine from current file location
        $basePath = $basePath ?? dirname(__DIR__, 2);

        // Define constant for global access if not already defined
        if (!defined('APP_BASE_PATH')) {
            define('APP_BASE_PATH', $basePath);
        }

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

        return $application;
    }
}