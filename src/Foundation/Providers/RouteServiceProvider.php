<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Ody\Core\Foundation\Support\Config;
use Ody\Core\Foundation\Loaders\RouteLoader;
use Ody\Core\Foundation\Middleware\Middleware;
use Ody\Core\Foundation\Middleware\Resolvers\MiddlewareResolverFactory;
use Ody\Core\Foundation\Router;
use Psr\Log\LoggerInterface;

/**
 * Service provider for routes
 */
class RouteServiceProvider extends AbstractServiceProvider
{
    /**
     * @var string Base path for route files
     */
    protected $routesPath = '';

    /**
     * @var MiddlewareResolverFactory
     */
    protected $resolverFactory;

    /**
     * Register custom services
     *
     * @return void
     * @throws BindingResolutionException
     */
    protected function registerServices(): void
    {
        // Register RouteLoader
        $this->container->singleton(RouteLoader::class, function ($container) {
            $router = $container->make(Router::class);
            $middleware = $container->make(Middleware::class);

            return new RouteLoader($router, $middleware, $container);
        });

        // Set routes path based on config
        $config = $this->container->make(Config::class);
        $this->routesPath = $config->get('app.routes.path', route_path());

        // Register resolver factory
        $this->container->singleton(MiddlewareResolverFactory::class, function ($container) {
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
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        $routeLoader = $container->make(RouteLoader::class);
        $this->resolverFactory = $container->make(MiddlewareResolverFactory::class);

        // Register named middleware and load routes
        $this->registerNamedMiddleware($container);
        $this->loadRouteFiles($routeLoader);
    }

    /**
     * Register named middleware used in routes
     *
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
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