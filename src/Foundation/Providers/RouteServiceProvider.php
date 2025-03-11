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

        // Set routes path based on config or default
        /** @var Config $config */
        $config = $this->container->make(Config::class);
        $this->routesPath = $config->get('app.routes_path', route_path());
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

        $this->registerNamedMiddleware($container);
        $this->loadRouteFiles($routeLoader, $logger);
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

        // Auth middleware with guard support
        $middleware->addNamed('auth', function (ServerRequestInterface $request, callable $next) use ($container, $logger) {
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
        });

        // Auth:api middleware
        $middleware->addNamed('auth:api', function (ServerRequestInterface $request, callable $next) use ($container, $logger) {
            // Create auth middleware with 'api' guard
            $authMiddleware = new AuthMiddleware('api', $logger);

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
        });

        // Auth:jwt middleware
        $middleware->addNamed('auth:jwt', function (ServerRequestInterface $request, callable $next) use ($container, $logger) {
            // Create auth middleware with 'jwt' guard
            $authMiddleware = new AuthMiddleware('jwt', $logger);

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
        });

        // Role middleware
        $middleware->addNamed('role', function (ServerRequestInterface $request, callable $next) use ($logger) {
            $role = 'user';

            // If it's our custom Request class with middlewareParams
            if ($request instanceof Request && isset($request->middlewareParams['role'])) {
                $role = $request->middlewareParams['role'];
            }

            // Create role middleware
            $roleMiddleware = new RoleMiddleware($role, $logger);

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

            // Process the request through the role middleware
            return $roleMiddleware->process($request, $handler);
        });

        // Throttle middleware
        $middleware->addNamed('throttle', function (ServerRequestInterface $request, callable $next) {
            $rate = '60,1'; // Default rate

            // If it's our custom Request class with middlewareParams
            if ($request instanceof Request && isset($request->middlewareParams['throttle'])) {
                $rate = $request->middlewareParams['throttle'];
            }

            list($maxAttempts, $minutes) = explode(',', $rate);

            // Create throttle middleware
            $throttleMiddleware = new ThrottleMiddleware((int)$maxAttempts, (int)$minutes);

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

            // Process the request through the throttle middleware
            return $throttleMiddleware->process($request, $handler);
        });
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