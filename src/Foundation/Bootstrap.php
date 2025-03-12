<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\Core\Foundation;

use Illuminate\Container\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Ody\Core\Foundation\Loaders\ServiceProviderLoader;
use Ody\Core\Foundation\Providers\ConfigServiceProvider;
use Ody\Core\Foundation\Providers\FacadeServiceProvider;
use Ody\Core\Foundation\Providers\ServiceProviderManager;
use Ody\Core\Foundation\Support\Config;
use Ody\Core\Foundation\Support\Env;
use Ody\Core\Foundation\Logging\LogManager;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Application Bootstrap
 */
class Bootstrap
{
    /**
     * Initialize the application
     *
     * @param Container|null $container
     * @param string|null $configPath
     * @param string|null $environment
     * @return Application
     */
    public static function init(?Container $container = null, ?string $configPath = null, ?string $environment = null): Application
    {
        // Define base path if not already defined
        if (!defined('APP_BASE_PATH')) {
            define('APP_BASE_PATH', dirname(__DIR__, 2));
        }

        // Create container if not provided
        $container = $container ?? new Container();
        Container::setInstance($container);

        // Initialize environment
        $env = self::initEnvironment($container, $environment);

        // Initialize configuration
        $config = self::initConfiguration($container, $configPath);

        // Initialize PSR-17 factories
        self::initPsr17Factories($container);

        // Initialize service providers
        $application = self::initServiceProviders($container);

        return $application;
    }

    /**
     * Initialize environment
     *
     * @param Container $container
     * @param string|null $environment
     * @return Env
     */
    private static function initEnvironment(Container $container, ?string $environment = null): Env
    {
        $env = new Env(APP_BASE_PATH);
        $env->load($environment ?? env('APP_ENV', 'production'));

        $container->instance(Env::class, $env);

        return $env;
    }

    /**
     * Initialize configuration
     *
     * @param Container $container
     * @param string|null $configPath
     * @return Config
     */
    private static function initConfiguration(Container $container, ?string $configPath = null): Config
    {
        $config = new Config();
        $configPath = $configPath ?? env('CONFIG_PATH', APP_BASE_PATH . '/config');

        $config->loadFromDirectory($configPath);

        $container->instance('config', $config);
        $container->instance(Config::class, $config);

        return $config;
    }

    /**
     * Initialize PSR-17 factories
     *
     * @param Container $container
     * @return void
     */
    private static function initPsr17Factories(Container $container): void
    {
        $psr17Factory = new Psr17Factory();
        $container->instance(Psr17Factory::class, $psr17Factory);

        $container->instance(ServerRequestFactoryInterface::class, $psr17Factory);
        $container->instance(ResponseFactoryInterface::class, $psr17Factory);
        $container->instance(StreamFactoryInterface::class, $psr17Factory);
        $container->instance(UploadedFileFactoryInterface::class, $psr17Factory);
        $container->instance(UriFactoryInterface::class, $psr17Factory);
    }

    /**
     * Initialize service providers
     *
     * @param Container $container
     * @param Config $config
     * @return Application
     */
    private static function initServiceProviders(Container $container): Application
    {
        // Create service provider manager
        $providerManager = new ServiceProviderManager($container);
        $container->instance(ServiceProviderManager::class, $providerManager);

        // Register the ConfigServiceProvider first - it needs to be loaded before
        // other providers as they may depend on configuration
        $configProvider = new ConfigServiceProvider();
        $providerManager->register($configProvider);
        $providerManager->bootProvider($configProvider);

        // Register the FacadeServiceProvider early to allow facades to work
        // throughout the application bootstrap process
        $facadeProvider = new FacadeServiceProvider();
        $providerManager->register($facadeProvider);
        $providerManager->bootProvider($facadeProvider);

        // Create and use the service provider loader
        // TODO: ServiceProviderLoader deprecated
        $serviceProviderLoader = new ServiceProviderLoader($container, $providerManager, app('config'));
        $container->instance(ServiceProviderLoader::class, $serviceProviderLoader);

        // Register and boot all providers defined in config
        $serviceProviderLoader->register();
        $serviceProviderLoader->boot();

        // Return application instance from container
        return $container->make(Application::class);
    }
}