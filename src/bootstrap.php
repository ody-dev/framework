<?php
/**
* Application bootstrap file
* Initializes container and loads service providers
*/

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use Ody\Core\Logger;
use Ody\Core\ServiceProviderManager;
use Ody\Core\Providers\ApplicationServiceProvider;
use Ody\Core\Providers\DatabaseServiceProvider;
use Ody\Core\Providers\RouteServiceProvider;
use Ody\Core\Providers\MiddlewareServiceProvider;
use Illuminate\Container\Container;

/**
* Bootstrap the application
*
* @param string $configFile Path to configuration file
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
        'log_file' => __DIR__ . '/logs/api.log',
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
            'password' => 'x3tjVsnfWs8K'
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

// Return bootstrapped container
return bootstrap();