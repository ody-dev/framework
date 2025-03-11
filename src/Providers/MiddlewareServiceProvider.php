<?php
namespace Ody\Core\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Ody\Core\Logger;
use Ody\Core\Middleware\Middleware;
use Ody\Core\Middleware\RouteMiddlewareManager;
use Swoole\HTTP\Request;
use Swoole\HTTP\Response;

class MiddlewareServiceProvider extends AbstractServiceProvider
{
    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Register RouteMiddlewareManager
        $this->container->singleton(RouteMiddlewareManager::class, function ($container) {
            return new RouteMiddlewareManager();
        });

        // Register Middleware with RouteMiddlewareManager
        $this->container->singleton(Middleware::class, function ($container) {
            $routeMiddlewareManager = $container->make(RouteMiddlewareManager::class);
            return new Middleware($routeMiddlewareManager);
        });
    }

    /**
     * Bootstrap middleware
     *
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        $middleware = $container->make(Middleware::class);
        $logger = $container->make(Logger::class);

        // Register named middleware
        $this->registerNamedMiddleware($middleware, $logger);

        // Register global middleware
        $this->registerCorsMiddleware($middleware);
        $this->registerJsonBodyParserMiddleware($middleware);

        // Register route-specific middleware
        $this->registerRouteMiddleware($middleware, $logger);
    }

    /**
     * Register named middleware for use in routes
     *
     * @param Middleware $middleware
     * @param Logger $logger
     * @return void
     */
    protected function registerNamedMiddleware(Middleware $middleware, Logger $logger): void
    {
        // Register auth middleware
        $middleware->addNamed('auth', function (Request $request, Response $response, callable $next) use ($logger) {
            $authHeader = $request->header['authorization'] ?? '';

            // Simple token check - replace with proper auth in production
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
                if ($token === 'valid-token') {
                    $next($request, $response);
                    return;
                }
            }

            $logger->warning('Unauthorized access attempt', [
                'ip' => $request->server['remote_addr'] ?? 'unknown',
                'uri' => $request->server['request_uri'] ?? '/'
            ]);

            $response->status(401);
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode([
                'error' => 'Unauthorized'
            ]));
        });

        // Register logging middleware
        $middleware->addNamed('logging', function (Request $request, Response $response, callable $next) use ($logger) {
            $startTime = microtime(true);

            $next($request, $response);

            $duration = microtime(true) - $startTime;
            $logger->info('Request completed', [
                'method' => $request->server['request_method'] ?? 'UNKNOWN',
                'uri' => $request->server['request_uri'] ?? '/',
                'status' => $response->statusCode ?? 200,
                'duration' => round($duration * 1000, 2) . 'ms'
            ]);
        });
    }

    /**
     * Register CORS middleware
     *
     * @param Middleware $middleware
     * @return void
     */
    protected function registerCorsMiddleware(Middleware $middleware): void
    {
        $middleware->add(function (Request $request, Response $response, callable $next) {
            $response->header('Access-Control-Allow-Origin', '*');
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

            if ($request->server['request_method'] === 'OPTIONS') {
                $response->status(200);
                $response->end();
                return;
            }

            $next($request, $response);
        });
    }

    /**
     * Register JSON body parser middleware
     *
     * @param Middleware $middleware
     * @return void
     */
    protected function registerJsonBodyParserMiddleware(Middleware $middleware): void
    {
        $middleware->add(function (Request $request, Response $response, callable $next) {
            if (isset($request->header['content-type']) &&
                strpos($request->header['content-type'], 'application/json') !== false) {
                $request->parsedBody = json_decode($request->rawContent(), true) ?? [];
            }

            $next($request, $response);
        });
    }

    /**
     * Register route-specific middleware
     *
     * @param Middleware $middleware
     * @param Logger $logger
     * @return void
     */
    protected function registerRouteMiddleware(Middleware $middleware, Logger $logger): void
    {
        // Apply 'auth' middleware to all /users routes
//        $middleware->addToGroup('*:/users*', 'auth');

        // Apply 'auth' middleware to specific routes
        $middleware->addToRoute('PUT', '/profile', 'auth');
        $middleware->addToRoute('POST', '/logout', 'auth');

        // Apply logging middleware to all routes
        $middleware->addToGroup('*:*', 'logging');

        // Don't require auth for these specific endpoints
        $middleware->addToRoute('POST', '/login', function (Request $request, Response $response, callable $next) {
            // Just pass through without auth check
            $next($request, $response);
        });

        $middleware->addToRoute('POST', '/register', function (Request $request, Response $response, callable $next) {
            // Just pass through without auth check
            $next($request, $response);
        });

        $middleware->addToRoute('GET', '/health', function (Request $request, Response $response, callable $next) {
            // Just pass through without auth check
            $next($request, $response);
        });
    }
}