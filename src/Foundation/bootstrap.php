<?php
/**
 * Application bootstrap file
 * Initializes container and loads service providers
 */

// Load Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Container\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Ody\Core\Foundation\Loaders\ServiceProviderLoader;
use Ody\Core\Foundation\Providers\ConfigServiceProvider;
use Ody\Core\Foundation\Providers\ServiceProviderManager;
use Ody\Core\Foundation\Support\Config;
use Ody\Core\Foundation\Support\Env;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Bootstrap the application
 *
 * @param string|null $configPath Path to configuration directory
 * @param string|null $environment Environment name
 * @return Container
 */
function bootstrap(string $configPath = null, string $environment = null): Container
{
    // Define base path if not already defined
    if (!defined('APP_BASE_PATH')) {
        define('APP_BASE_PATH', dirname(__DIR__, 2));
    }

    // Initialize environment variables
    $env = new Env(APP_BASE_PATH);
    $env->load($environment ?? env('APP_ENV', 'production'));

    // Create container
    $container = new Container();

    // Set as global container
    Container::setInstance($container);

    // Register environment in container
    $container->instance(Env::class, $env);

    // Initialize configuration
    $config = initializeConfig($container, $configPath);

    // Register PSR-17 factories
    registerPsr17Factories($container);

    // Create service provider manager
    $providerManager = new ServiceProviderManager($container);
    $container->instance(ServiceProviderManager::class, $providerManager);

    // Register the ConfigServiceProvider first as it's required by other providers
    $providerManager->register(new ConfigServiceProvider());
    $providerManager->bootProvider(new ConfigServiceProvider());

    // Create and use the service provider loader
    $serviceProviderLoader = new ServiceProviderLoader($container, $providerManager, $config);
    $container->instance(ServiceProviderLoader::class, $serviceProviderLoader);

    // Register and boot all providers defined in config
    $serviceProviderLoader->register();
    $serviceProviderLoader->boot();

    return $container;
}

/**
 * Initialize and load configuration
 *
 * @param Container $container
 * @param string|null $configPath
 * @return Config
 */
function initializeConfig(Container $container, ?string $configPath = null): Config
{
    // Initialize configuration
    $config = new Config();

    // Set configuration path
    $configPath = $configPath ?? env('CONFIG_PATH', APP_BASE_PATH . '/config');

    // Ensure config directory exists
    if (!is_dir($configPath)) {
        mkdir($configPath, 0755, true);
    }

    // Load configuration files
    $config->loadFromDirectory($configPath);

    // Register configuration in container
    $container->instance('config', $config);
    $container->instance(Config::class, $config);

    // Ensure storage/logs directory exists
    $logDir = storage_path('logs');
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    return $config;
}

/**
 * Register PSR-17 factories in the container
 *
 * @param Container $container
 * @return void
 */
function registerPsr17Factories(Container $container): void
{
    // Create and register Nyholm's PSR-17 factory
    $psr17Factory = new Psr17Factory();
    $container->instance(Psr17Factory::class, $psr17Factory);

    // Bind PSR-17 interfaces to the factory
    $container->instance(ServerRequestFactoryInterface::class, $psr17Factory);
    $container->instance(ResponseFactoryInterface::class, $psr17Factory);
    $container->instance(StreamFactoryInterface::class, $psr17Factory);
    $container->instance(UploadedFileFactoryInterface::class, $psr17Factory);
    $container->instance(UriFactoryInterface::class, $psr17Factory);
}

// Return bootstrapped container
return bootstrap();