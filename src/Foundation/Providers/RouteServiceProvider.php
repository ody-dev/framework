<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Ody\Core\Foundation\Support\Config;
use Ody\Core\Foundation\Loaders\RouteLoader;
use Ody\Core\Foundation\Logger;
use Ody\Core\Foundation\Middleware\Middleware;
use Ody\Core\Foundation\Middleware\AuthMiddleware;
use Ody\Core\Foundation\Middleware\RoleMiddleware;
use Ody\Core\Foundation\Middleware\ThrottleMiddleware;
use Ody\Core\Foundation\Router;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Http\RequestHandler;

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
        $logger = $container->make(Logger::class);
        $routeLoader = $container->make(RouteLoader::class);

        try {
            $this->registerNamedMiddleware($container);
            $this->loadRouteFiles($routeLoader, $logger);
        } catch (\Throwable $e) {
            $logger->error('Failed to load routes', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // Rethrow in development, swallow in production
            if (env('APP_DEBUG', false)) {
                throw $e;
            }
        }
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
        $logger = $container->make(Logger::class);
        $config = $container->make(Config::class);

        // Register configured middleware
        $namedMiddleware = $config->get('app.routes.middleware.named', [
            'auth' => function (ServerRequestInterface $request, callable $next) use ($container, $logger) {
                // Create auth middleware with 'web' guard
                $authMiddleware = new AuthMiddleware('web', $logger);

                // Create a request handler for the next middleware
                $handler = new class($next) implements RequestHandlerInterface {
                    private $next;

                    public function __construct(callable $next) {
                        $this->next = $next;
                    }

                    public function handle(ServerRequestInterface $request): ResponseInterface {
                        return call_user_func($this->next, $request);
                    }
                };

                // Process the request through the auth middleware
                return $authMiddleware->process($request, $handler);
            },
            'auth:api' => function (ServerRequestInterface $request, callable $next) use ($container, $logger) {
                $authMiddleware = new AuthMiddleware('api', $logger);
                $handler = $this->createNextHandler($next);
                return $authMiddleware->process($request, $handler);
            },
            'auth:jwt' => function (ServerRequestInterface $request, callable $next) use ($container, $logger) {
                $authMiddleware = new AuthMiddleware('jwt', $logger);
                $handler = $this->createNextHandler($next);
                return $authMiddleware->process($request, $handler);
            },
            'role' => function (ServerRequestInterface $request, callable $next) use ($logger) {
                $role = 'user';

                // If it's our custom Request class with middlewareParams
                if ($request instanceof Request && isset($request->middlewareParams['role'])) {
                    $role = $request->middlewareParams['role'];
                }

                // Create role middleware
                $roleMiddleware = new RoleMiddleware($role, $logger);
                $handler = $this->createNextHandler($next);
                return $roleMiddleware->process($request, $handler);
            },
            'throttle' => function (ServerRequestInterface $request, callable $next) {
                $rate = '60,1'; // Default rate

                // If it's our custom Request class with middlewareParams
                if ($request instanceof Request && isset($request->middlewareParams['throttle'])) {
                    $rate = $request->middlewareParams['throttle'];
                }

                list($maxAttempts, $minutes) = explode(',', $rate);

                // Create throttle middleware
                $throttleMiddleware = new ThrottleMiddleware((int)$maxAttempts, (int)$minutes);
                $handler = $this->createNextHandler($next);
                return $throttleMiddleware->process($request, $handler);
            }
        ]);

        // Register each middleware
        foreach ($namedMiddleware as $name => $middlewareHandler) {
            $middleware->addNamed($name, $middlewareHandler);
        }
    }

    /**
     * Create a handler for the next middleware
     *
     * @param callable $next
     * @return RequestHandlerInterface
     */
    protected function createNextHandler(callable $next): RequestHandlerInterface
    {
        return new class($next) implements RequestHandlerInterface {
            private $next;

            public function __construct(callable $next) {
                $this->next = $next;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface {
                return call_user_func($this->next, $request);
            }
        };
    }

    /**
     * Load route files
     *
     * @param RouteLoader $routeLoader
     * @param Logger $logger
     * @return void
     */
    protected function loadRouteFiles(RouteLoader $routeLoader, Logger $logger): void
    {
        // Ensure routes directory exists
        if (!is_dir($this->routesPath)) {
            $logger->warning('Routes directory not found', ['path' => $this->routesPath]);
            return;
        }

        // Load main routes file
        $mainRoutesFile = $this->routesPath . '/web.php';
        if (file_exists($mainRoutesFile)) {
            $logger->info('Loading main routes file', ['file' => $mainRoutesFile]);
            $routeLoader->load($mainRoutesFile);
        }

        // Load API routes file
        var_dump($this->routesPath);
        $apiRoutesFile = $this->routesPath . '/api.php';
        if (file_exists($apiRoutesFile)) {
            $logger->info('Loading API routes file', ['file' => $apiRoutesFile]);
            $routeLoader->load($apiRoutesFile);
        }

        // Load additional route files from routes directory
        $additionalFiles = $routeLoader->loadDirectory($this->routesPath);
        $logger->info('Loaded additional route files', ['count' => $additionalFiles]);

        // Get all loaded files for debugging
        $logger->debug('All loaded route files', [
            'files' => $routeLoader->getLoadedFiles()
        ]);
    }
}