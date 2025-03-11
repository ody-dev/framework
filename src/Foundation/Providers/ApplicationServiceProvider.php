<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Application;
use Ody\Core\Foundation\Logger;
use Ody\Core\Foundation\Middleware\Middleware;
use Ody\Core\Foundation\Middleware\RouteMiddlewareManager;
use Ody\Core\Foundation\Router;

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
        Logger::class => null, // Custom registration in registerServices
        Application::class => null, // Custom registration in registerServices
        RouteMiddlewareManager::class => RouteMiddlewareManager::class,
    ];

    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Register middleware manager
        $this->container->singleton(RouteMiddlewareManager::class, function ($container) {
            return new RouteMiddlewareManager();
        });

        // Register middleware
        $this->container->singleton(Middleware::class, function ($container) {
            $routeMiddlewareManager = $container->make(RouteMiddlewareManager::class);
            return new Middleware($routeMiddlewareManager);
        });

        // Register logger with configuration
        $this->container->singleton(Logger::class, function ($container) {
            $config = $container->make('config');
            $logFile = $config['log_file'] ?? 'api.log';
            $logLevel = $config['log_level'] ?? Logger::LEVEL_INFO;

            return new Logger($logFile, $logLevel);
        });

        // Register router with container and middleware
        $this->container->singleton(Router::class, function ($container) {
            $middleware = $container->make(Middleware::class);
            return new Router($container, $middleware);
        });

        // Register application
        $this->container->singleton(Application::class, function ($container) {
            $router = $container->make(Router::class);
            $middleware = $container->make(Middleware::class);
            $logger = $container->make(Logger::class);

            return new Application($router, $middleware, $logger, $container);
        });
    }

    /**
     * Bootstrap any application services
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Add default middleware if needed
        $middleware = $container->make(Middleware::class);

        // Add global logging middleware
        $middleware->addNamed('logging', function ($request, $response, $next) use ($container) {
            $logger = $container->make(Logger::class);
            $logger->info('Request started', [
                'method' => $request->server['request_method'] ?? 'UNKNOWN',
                'uri' => $request->server['request_uri'] ?? '/',
            ]);

            $start = microtime(true);
            $next($request, $response);
            $duration = microtime(true) - $start;

            $logger->info('Request completed', [
                'duration' => round($duration * 1000, 2) . 'ms',
                'status' => $response->statusCode ?? 200,
            ]);
        });

        // Add global cors middleware
        $middleware->addNamed('cors', function ($request, $response, $next) {
            $response->header('Access-Control-Allow-Origin', '*');
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key');

            if ($request->server['request_method'] === 'OPTIONS') {
                $response->status(200);
                $response->end();
                return;
            }

            $next($request, $response);
        });

        // Register body parser middleware
        $middleware->addNamed('json', function ($request, $response, $next) {
            if (isset($request->header['content-type']) &&
                strpos($request->header['content-type'], 'application/json') !== false) {
                $request->parsedBody = json_decode($request->rawContent(), true) ?? [];
            }

            $next($request, $response);
        });

        // Apply global middlewares to all routes
        $middleware->addToGroup('*:*', 'cors');
        $middleware->addToGroup('*:*', 'json');
        $middleware->addToGroup('*:*', 'logging');
    }
}