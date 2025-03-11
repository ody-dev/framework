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
use Ody\Core\Foundation\Logger;
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
    Container::setInstance($container);
    $container->instance(Env::class, $env);

    // Initialize configuration
    $config = initializeConfig($container, $configPath);

    // Initialize logger
    $logger = initializeLogger($container, $config);

    // Register PSR-17 factories
    registerPsr17Factories($container);

    // Create service provider manager
    $providerManager = new ServiceProviderManager($container);
    $container->instance(ServiceProviderManager::class, $providerManager);

    // Register the ConfigServiceProvider first as it's required by other providers
    $configProvider = new ConfigServiceProvider();
    $providerManager->register($configProvider);
    $providerManager->bootProvider($configProvider);

    // Create and use the service provider loader
    $serviceProviderLoader = new ServiceProviderLoader($container, $providerManager, $config, $logger);
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

    // Load configuration files
    $config->loadFromDirectory($configPath);

    // Register configuration in container
    $container->instance('config', $config);
    $container->instance(Config::class, $config);

    return $config;
}

/**
 * Initialize logger
 *
 * @param Container $container
 * @param Config $config
 * @return Logger
 */
function initializeLogger(Container $container, Config $config): Logger
{
    $logFile = $config->get('logging.channels.file.path', APP_BASE_PATH . '/storage/logs/api.log');
    $logLevel = $config->get('logging.level', Logger::LEVEL_INFO);

    // Ensure directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logger = new Logger($logFile, $logLevel);
    $container->instance(Logger::class, $logger);
    $container->alias(Logger::class, 'logger');

    return $logger;
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