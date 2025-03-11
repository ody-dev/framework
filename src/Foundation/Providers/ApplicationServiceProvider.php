<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Ody\Core\Foundation\Application;
use Ody\Core\Foundation\Support\Config;
use Ody\Core\Foundation\Middleware\Middleware;
use Ody\Core\Foundation\Middleware\CorsMiddleware;
use Ody\Core\Foundation\Middleware\JsonBodyParserMiddleware;
use Ody\Core\Foundation\Middleware\LoggingMiddleware;
use Ody\Core\Foundation\Router;
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
class ApplicationServiceProvider extends AbstractServiceProvider
{
    /**
     * Services that should be registered as singletons
     *
     * @var array
     */
    protected $singletons = [
        Middleware::class => null, // Custom registration in registerServices
        Router::class => null, // Custom registration in registerServices
        Application::class => null, // Custom registration in registerServices
        CorsMiddleware::class => null, // Custom registration in registerServices
    ];

    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Register PSR-17 factories
        $this->registerPsr17Factories();

        // Register middleware
        $this->container->singleton(Middleware::class, function ($container) {
            return new Middleware($container);
        });

        // Register router with container and middleware
        $this->container->singleton(Router::class, function ($container) {
            $middleware = $container->make(Middleware::class);
            return new Router($container, $middleware);
        });

        // Register PSR-15 middleware classes
        $this->registerPsr15Middleware();

        // Register application
        $this->container->singleton(Application::class, function ($container) {
            $router = $container->make(Router::class);
            $middleware = $container->make(Middleware::class);
            $logger = $container->make(LoggerInterface::class);

            return new Application($router, $middleware, $logger, $container);
        });
    }

    /**
     * Register PSR-17 factories
     *
     * @return void
     */
    private function registerPsr17Factories(): void
    {
        // Use Nyholm's PSR-17 factory
        $this->container->singleton(Psr17Factory::class, function () {
            return new Psr17Factory();
        });

        // Bind PSR-17 interfaces to Nyholm implementation
        $this->container->singleton(ServerRequestFactoryInterface::class, function ($container) {
            return $container->make(Psr17Factory::class);
        });

        $this->container->singleton(ResponseFactoryInterface::class, function ($container) {
            return $container->make(Psr17Factory::class);
        });

        $this->container->singleton(StreamFactoryInterface::class, function ($container) {
            return $container->make(Psr17Factory::class);
        });

        $this->container->singleton(UploadedFileFactoryInterface::class, function ($container) {
            return $container->make(Psr17Factory::class);
        });

        $this->container->singleton(UriFactoryInterface::class, function ($container) {
            return $container->make(Psr17Factory::class);
        });
    }

    /**
     * Register PSR-15 middleware implementations
     *
     * @return void
     */
    private function registerPsr15Middleware(): void
    {
        // Register CORS middleware
        $this->container->singleton(CorsMiddleware::class, function ($container) {
            /** @var Config $config */
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
        $this->container->singleton(JsonBodyParserMiddleware::class, function () {
            return new JsonBodyParserMiddleware();
        });

        // Register logging middleware
        $this->container->singleton(LoggingMiddleware::class, function ($container) {
            $logger = $container->make(LoggerInterface::class);
            return new LoggingMiddleware($logger);
        });
    }

    /**
     * Bootstrap any application services
     *
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        // No need to register global middleware here if it's being registered
        // through middleware configuration and MiddlewareServiceProvider
    }
}