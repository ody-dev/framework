<?php
namespace Ody\Foundation\Providers;

use Ody\Foundation\Application;
use Ody\Foundation\Middleware\MiddlewareRegistry;
use Ody\Foundation\Support\Config;
use Ody\Foundation\Middleware\CorsMiddleware;
use Ody\Foundation\Middleware\JsonBodyParserMiddleware;
use Ody\Foundation\Middleware\LoggingMiddleware;
use Ody\Foundation\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service provider for core application services
 */
class ApplicationServiceProvider extends ServiceProvider
{
    /**
     * Services that should be registered as singletons
     *
     * @var array
     */
    protected array $singletons = [
        Application::class => null,
        Router::class => null,
        Psr17Factory::class => null,
        ServerRequestFactoryInterface::class => Psr17Factory::class,
        ResponseFactoryInterface::class => Psr17Factory::class,
        StreamFactoryInterface::class => Psr17Factory::class,
        UploadedFileFactoryInterface::class => Psr17Factory::class,
        UriFactoryInterface::class => Psr17Factory::class,
        CorsMiddleware::class => null,
        JsonBodyParserMiddleware::class => null,
        LoggingMiddleware::class => null
    ];

    /**
     * Tags for organizing services
     *
     * @var array
     */
    protected array $tags = [
        'psr7' => [
            Psr17Factory::class,
            ServerRequestFactoryInterface::class,
            ResponseFactoryInterface::class,
            StreamFactoryInterface::class,
            UploadedFileFactoryInterface::class,
            UriFactoryInterface::class
        ],
        'middleware' => [
            CorsMiddleware::class,
            JsonBodyParserMiddleware::class,
            LoggingMiddleware::class
        ]
    ];

    /**
     * Register custom services
     *
     * @return void
     */
    public function register(): void
    {
        // Register router with container and middleware
        $this->registerSingleton(Router::class, function ($container) {
            $middlewareRegistry = $container->make(MiddlewareRegistry::class);
            return new Router($container, $middlewareRegistry);
        });

        // Register PSR-15 middleware classes
        $this->registerPsr15Middleware();

        // Register application
        $this->registerSingleton(Application::class, function ($container) {
            $router = $container->make(Router::class);
            $middlewareRegistry = $container->make(MiddlewareRegistry::class);
            $logger = $container->make(LoggerInterface::class);

            return new Application($router, $middlewareRegistry, $logger, $container);
        });
    }

    public function boot(): void
    {
        // TODO: Implement boot() method.
    }

    /**
     * Register PSR-15 middleware implementations
     *
     * @return void
     */
    private function registerPsr15Middleware(): void
    {
        // Register CORS middleware
        $this->registerSingleton(CorsMiddleware::class, function ($container) {
            $config = $container->make(Config::class);
            $corsConfig = $config->get('cors', [
                'origin' => '*',
                'methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'headers' => 'Content-Type, Authorization, X-API-Key',
                'max_age' => 86400
            ]);

            return new CorsMiddleware($corsConfig);
        });

        // Register JSON body parser middleware
        $this->registerSingleton(JsonBodyParserMiddleware::class, function () {
            return new JsonBodyParserMiddleware();
        });

        // Register logging middleware
        $this->registerSingleton(LoggingMiddleware::class, function ($container) {
            $logger = $container->make(LoggerInterface::class);
            return new LoggingMiddleware($logger);
        });
    }
}