<?php
declare(strict_types=1);

namespace Ody\Foundation;

use Nyholm\Psr7\Factory\Psr17Factory;
use Ody\Container\Container;
use Ody\Foundation\Providers\ServiceProviderManager;
use Ody\Foundation\Support\Config;
use Ody\Foundation\Support\Env;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Application Bootstrap
 *
 * Simplified version with cleaner initialization flow
 */
class Bootstrap
{
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

        // 3. Load environment and config in one step (since they're closely related)
        $config = self::initEnvironmentAndConfig($container, $basePath, $environment);

        // 4. Create and bootstrap the application (moving most logic to Application)
        $application = self::createApplication($container, $config);

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
     * Initialize environment and configuration
     * (Combined for efficiency since they're dependent)
     *
     * @param Container $container
     * @param string $basePath
     * @param string|null $environment
     * @return Config
     */
    private static function initEnvironmentAndConfig(Container $container, string $basePath, ?string $environment = null): Config
    {
        // 1. Initialize environment
        $env = new Env($basePath);
        // Handle the case where getenv returns false
        $envName = $environment ?? (getenv('APP_ENV') !== false ? getenv('APP_ENV') : 'production');
        $env->load($envName);
        $container->instance(Env::class, $env);

        // 2. Initialize configuration
        $config = new Config();
        $configPath = env('CONFIG_PATH', $basePath . '/config');
        $config->loadFromDirectory($configPath);

        // Register in container
        $container->instance('config', $config);
        $container->instance(Config::class, $config);

        // 3. Register temporary logger (will be replaced by proper logger later)
        $container->singleton(LoggerInterface::class, function() {
            return new NullLogger();
        });

        return $config;
    }

    /**
     * Create and bootstrap the application
     *
     * @param Container $container
     * @param Config $config
     * @return Application
     */
    private static function createApplication(Container $container, Config $config): Application
    {
        self::registerCoreServices($container);

        $providerManager = new ServiceProviderManager($container, $config);
        $container->instance(ServiceProviderManager::class, $providerManager);

        $application = new Application($container, $providerManager);
        $container->instance(Application::class, $application);
        $application->bootstrap();

        return $application;
    }

    /**
     * Register minimal core services required by Application
     * (Others will be registered by service providers)
     *
     * @param Container $container
     * @return void
     */
    private static function registerCoreServices(Container $container): void
    {
        // Only register what's absolutely necessary for Application construction
        // PSR-17 factories are registered here because they're fundamental to HTTP handling
        // and used by multiple components

        $psr17Factory = new Psr17Factory();
        $container->instance(Psr17Factory::class, $psr17Factory);

        // Use interface aliasing to PSR factory implementations
        $interfaces = [
            'Psr\Http\Message\ServerRequestFactoryInterface',
            'Psr\Http\Message\ResponseFactoryInterface',
            'Psr\Http\Message\StreamFactoryInterface',
            'Psr\Http\Message\UploadedFileFactoryInterface',
            'Psr\Http\Message\UriFactoryInterface'
        ];

        foreach ($interfaces as $interface) {
            $container->singleton($interface, function() use ($psr17Factory) {
                return $psr17Factory;
            });
        }
    }
}