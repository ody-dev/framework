<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Ody\Core\Foundation\Loaders\RouteLoader;
use Ody\Core\Foundation\Logger;
use Ody\Core\Foundation\Middleware\Middleware;
use Ody\Core\Foundation\Router;
use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Http\Response;

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
        $config = $this->container->make('config');
        $this->routesPath = $config['routes_path'] ?? __DIR__ . '/../../../routes';
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
        $middleware->addNamed('auth', function ($request, $response, $next) use ($container, $logger) {
            $guard = $request->middlewareParams['auth'] ?? 'web';

            $authHeader = $request->header['authorization'] ?? '';

            // Authenticate based on the guard
            if ($guard === 'api') {
                // API token auth
                if (strpos($authHeader, 'Bearer ') === 0) {
                    $token = substr($authHeader, 7);
                    if ($token === 'valid-api-token') {
                        $next($request, $response);
                        return;
                    }
                }
            } elseif ($guard === 'jwt') {
                // JWT auth
                if (strpos($authHeader, 'Bearer ') === 0) {
                    $token = substr($authHeader, 7);
                    // Verify JWT token (simplified)
                    if (strpos($token, 'jwt-') === 0) {
                        $next($request, $response);
                        return;
                    }
                }
            } else {
                // Regular auth (web)
                if (strpos($authHeader, 'Bearer ') === 0) {
                    $token = substr($authHeader, 7);
                    if ($token === 'valid-token') {
                        $next($request, $response);
                        return;
                    }
                }
            }

            $logger->warning('Unauthorized access attempt', [
                'guard' => $guard,
                'ip' => $request->server['remote_addr'] ?? 'unknown'
            ]);

            $response->status(401);
            $response->header('Content-Type', 'application/json');

            $response->end(json_encode([
                'error' => 'Unauthorized',
                'guard' => $guard
            ]));
        });

        // Role middleware
        $middleware->addNamed('role', function ($request, $response, $next) {
            $requiredRole = $request->middlewareParams['role'] ?? '';

            // Check if user has the required role
            // In a real app, fetch user role from database or JWT token
            $userRole = 'admin'; // Example

            if ($userRole === $requiredRole) {
                $next($request, $response);
                return;
            }

            $response->status(403);
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode([
                'error' => 'Forbidden - Insufficient permissions'
            ]));
        });

        // Throttle middleware
        $middleware->addNamed('throttle', function ($request, $response, $next) {
            $rate = $request->middlewareParams['throttle'] ?? '60,1';
            list($maxAttempts, $minutes) = explode(',', $rate);

            // Simplified throttling check (for demo purposes)
            $response->header('X-RateLimit-Limit', $maxAttempts);
            $response->header('X-RateLimit-Remaining', $maxAttempts - 1);

            $next($request, $response);
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