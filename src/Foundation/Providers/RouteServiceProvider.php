<?php
namespace Ody\Foundation\Providers;

use Ody\Container\Container;
use Ody\Foundation\Support\Config;
use Ody\Foundation\Loaders\RouteLoader;
use Ody\Foundation\Middleware\Middleware;
use Ody\Foundation\Middleware\Resolvers\MiddlewareResolverFactory;
use Ody\Foundation\Router;
use Psr\Log\LoggerInterface;

/**
 * Service provider for routes
 */
class RouteServiceProvider extends AbstractServiceProviderInterface
{
    /**
     * Services that should be registered as singletons
     *
     * @var array
     */
    protected $singletons = [
        RouteLoader::class => null,
        MiddlewareResolverFactory::class => null
    ];

    /**
     * @var string Base path for route files
     */
    protected $routesPath = '';

    /**
     * @var MiddlewareResolverFactory
     */
    protected $resolverFactory;

    /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides(): array
    {
        return array_merge(parent::provides(), [
            RouteLoader::class,
            MiddlewareResolverFactory::class
        ]);
    }

    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        error_log('RouteServiceProvider::registerServices() called');
        // Register RouteLoader
        $this->registerSingleton(RouteLoader::class, function ($container) {
            $router = $container->make(Router::class);
            $middleware = $container->make(Middleware::class);

            return new RouteLoader($router, $middleware, $container);
        });

        // Set routes path based on config
        $config = $this->make(Config::class);
        $this->routesPath = $config->get('app.routes.path', route_path());

        // Register resolver factory
        $this->registerSingleton(MiddlewareResolverFactory::class, function ($container) {
            $logger = $container->make(LoggerInterface::class);
            $config = $container->make(Config::class);

            return new MiddlewareResolverFactory($logger, $container, $config);
        });
    }

    /**
     * Bootstrap routes
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        $routeLoader = $this->make(RouteLoader::class);
        $this->resolverFactory = $this->make(MiddlewareResolverFactory::class);

        // Register named middleware and load routes
        $this->registerNamedMiddleware($container);
        $this->loadRouteFiles($routeLoader);
    }

    /**
     * Register named middleware used in routes
     *
     * @param Container $container
     * @return void
     */
    protected function registerNamedMiddleware(Container $container): void
    {
        $middleware = $container->make(Middleware::class);
        $config = $container->make(Config::class);

        // Get middleware definitions from config
        $namedMiddleware = $config->get('app.middleware.named', []);

        // Register each middleware from configuration
        array_walk($namedMiddleware, function ($middlewareClass, $name) use (&$middleware) {
            $resolvedMiddleware = $this->resolverFactory->resolve($name);
            $middleware->addNamed($name, $resolvedMiddleware);
        });
    }

    /**
     * Load route files
     *
     * @param RouteLoader $routeLoader
     * @return void
     */
    protected function loadRouteFiles(RouteLoader $routeLoader): void
    {
        // Ensure routes directory exists
        if (!is_dir($this->routesPath)) {
            return;
        }

        // Load main routes file
        $mainRoutesFile = $this->routesPath . '/web.php';
        if (file_exists($mainRoutesFile)) {
            $routeLoader->load($mainRoutesFile);
        }

        // Load API routes file
        $apiRoutesFile = $this->routesPath . '/api.php';
        if (file_exists($apiRoutesFile)) {
            $routeLoader->load($apiRoutesFile);
        }

        // Load additional route files from routes directory
        $routeLoader->loadDirectory($this->routesPath);
    }
}