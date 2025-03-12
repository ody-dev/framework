<?php
declare(strict_types=1);

namespace Ody\Foundation;

use Nyholm\Psr7\Factory\Psr17Factory;
use Ody\Container\Container;
use Ody\Container\Contracts\BindingResolutionException;
use Ody\Foundation\Providers\ConfigServiceProvider;
use Ody\Foundation\Providers\FacadeServiceProvider;
use Ody\Foundation\Providers\LoggingServiceProvider;
use Ody\Foundation\Providers\ServiceProviderManager;
use Ody\Foundation\Support\Config;
use Ody\Foundation\Support\Env;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

        // Initialize PSR-17 factories
        self::initPsr17Factories($container);

        // Register a temporary NullLogger to avoid circular dependencies
        $container->singleton(LoggerInterface::class, function() {
            return new NullLogger();
        });

        // Initialize configuration right away
        $config = self::initConfiguration($container, $configPath);

        // Initialize service providers
        $application = self::initServiceProviders($container, $config);

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
     * @throws BindingResolutionException
     */
    private static function initServiceProviders(Container $container, Config $config): Application
    {
        // Create service provider manager
        $providerManager = new ServiceProviderManager($container, $config);
        $container->instance(ServiceProviderManager::class, $providerManager);

        $providers = [
            ConfigServiceProvider::class,
            LoggingServiceProvider::class,
            FacadeServiceProvider::class,
        ];

        array_walk($providers, function ($provider) use ($providerManager) {
            $provider = new $provider();
            $providerManager->register($provider);
            $providerManager->bootProvider($provider);
        });

        // Register all providers defined in config
        $providerManager->registerConfigProviders('app.providers');

        // Boot all registered providers
        $providerManager->boot();

        // Return application instance from container
        return $container->make(Application::class);
    }
}