<?php
/**
 * Application bootstrap file
 * Initializes container and loads service providers
 */

// Load Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Container\Container;
use Ody\Core\Foundation\Logger;
use Ody\Core\Foundation\Providers\ApplicationServiceProvider;
use Ody\Core\Foundation\Providers\DatabaseServiceProvider;
use Ody\Core\Foundation\Providers\MiddlewareServiceProvider;
use Ody\Core\Foundation\Providers\RouteServiceProvider;
use Ody\Core\Foundation\Providers\ServiceProviderManager;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Bootstrap the application
 *
 * @param string|null $configFile Path to configuration file
 * @return Container
 */
function bootstrap(string $configFile = null): Container
{
    // Create container
    $container = new Container();

    // Set as global container
    Container::setInstance($container);

    // Load configuration
    $config = [];
    if ($configFile && file_exists($configFile)) {
        $config = require $configFile;
    } else {
        // Default configuration
        $config = [
            'log_file' => __DIR__ . '/../../storage/logs/api.log',
            'log_level' => Logger::LEVEL_INFO,
            'server' => [
                'host' => '0.0.0.0',
                'port' => 9501,
                'worker_num' => 4,
                'max_request' => 10000,
                'daemonize' => false,
            ],
            'database' => [
                'host' => 'localhost',
                'database' => 'clockwork',
                'username' => 'root',
                'password' => 'supersecretpassword!'
            ],
            'cors' => [
                'origin' => '*',
                'methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'headers' => 'Content-Type, Authorization, X-API-Key',
                'max_age' => 86400
            ]
        ];
    }

    // Register configuration in container
    $container->instance('config', $config);

    // Create logs directory if it doesn't exist
    $logDir = dirname($config['log_file']);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Register PSR-17 factories
    registerPsr17Factories($container);

    // Create service provider manager
    $providerManager = new ServiceProviderManager($container);
    $container->instance(ServiceProviderManager::class, $providerManager);

    // Register core service providers
    $providers = [
        ApplicationServiceProvider::class,
        DatabaseServiceProvider::class,
        MiddlewareServiceProvider::class,
        RouteServiceProvider::class,

        // Add custom providers here
        // App\Providers\CustomServiceProvider::class,
    ];

    // Register all providers
    $providerManager->registerProviders($providers);

    // Boot all service providers
    $providerManager->boot();

    return $container;
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