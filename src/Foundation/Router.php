<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\Foundation;

use FastRoute;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Ody\Container\Container;
use Ody\Foundation\Middleware\MiddlewareRegistry;
use function FastRoute\simpleDispatcher;

class Router
{
    /**
     * @var Dispatcher|null
     */
    private $dispatcher;

    /**
     * @var array
     */
    private $routes = [];

    /**
     * @var Container
     */
    private $container;

    /**
     * @var MiddlewareRegistry
     */
    private $middlewareRegistry;

    /**
     * Router constructor
     *
     * @param Container|null $container
     * @param MiddlewareRegistry|null $middlewareRegistry
     */
    public function __construct(
        ?Container          $container = null,
        ?MiddlewareRegistry $middlewareRegistry = null
    )
    {
        $this->container = $container ?? new Container();

        if ($middlewareRegistry) {
            $this->middlewareRegistry = $middlewareRegistry;
        } else if ($container && $container->has(MiddlewareRegistry::class)) {
            $this->middlewareRegistry = $container->make(MiddlewareRegistry::class);
        } else {
            $this->middlewareRegistry = new MiddlewareRegistry($this->container);
        }
    }

    /**
     * Create a route group with shared attributes
     *
     * @param array $attributes Group attributes (prefix, middleware)
     * @param callable $callback Function to define routes in the group
     * @return self
     */
    public function group(array $attributes, callable $callback): self
    {
        $prefix = $attributes['prefix'] ?? '';
        $middleware = $attributes['middleware'] ?? [];

        // Normalize prefix - ensure it starts with a slash if not empty
        if (!empty($prefix) && $prefix[0] !== '/') {
            $prefix = '/' . $prefix;
        }

        // Create a proxy router that will add the prefix to all routes
        $groupRouter = new class($this, $prefix, $middleware) {
            private $router;
            private $prefix;
            private $middleware;

            public function __construct($router, $prefix, $middleware)
            {
                $this->router = $router;
                $this->prefix = $prefix;
                $this->middleware = $middleware;
            }

            public function __call($method, $args)
            {
                // Only handle HTTP methods
                $httpMethods = ['get', 'post', 'put', 'patch', 'delete', 'options'];
                if (!in_array(strtolower($method), $httpMethods) || count($args) < 2) {
                    throw new \InvalidArgumentException("Unsupported method: {$method}");
                }

                // Extract path and handler
                $path = $args[0];
                $handler = $args[1];

                // Normalize the path - ensure it starts with a slash if not empty
                if (!empty($path) && $path[0] !== '/') {
                    $path = '/' . $path;
                }

                // FIX: Handle empty paths and trailing slashes properly
                // This is the key fix for the routing issue
                $fullPath = $this->combinePaths($this->prefix, $path);

                // Register the route
                $route = $this->router->{$method}($fullPath, $handler);

                // Apply group middleware to the route
                foreach ($this->middleware as $m) {
                    $route->middleware($m);
                }

                return $route;
            }

            // Add this new helper method to properly combine paths
            private function combinePaths($prefix, $path): string
            {
                // If path is empty, just return the prefix (without duplicate trailing slash)
                if ($path === '' || $path === '/') {
                    return rtrim($prefix, '/');
                }

                // Otherwise combine them properly avoiding double slashes
                return rtrim($prefix, '/') . $path;
            }

            // Define explicit methods for IDE auto-completion
            public function get($path, $handler) { return $this->__call('get', [$path, $handler]); }
            public function post($path, $handler) { return $this->__call('post', [$path, $handler]); }
            public function put($path, $handler) { return $this->__call('put', [$path, $handler]); }
            public function patch($path, $handler) { return $this->__call('patch', [$path, $handler]); }
            public function delete($path, $handler) { return $this->__call('delete', [$path, $handler]); }
            public function options($path, $handler) { return $this->__call('options', [$path, $handler]); }

            // Support nested groups
            public function group(array $attributes, callable $callback)
            {
                // Merge the prefixes
                $newPrefix = $this->prefix;
                if (isset($attributes['prefix'])) {
                    $prefixToAdd = $attributes['prefix'];
                    if (!empty($prefixToAdd) && $prefixToAdd[0] !== '/') {
                        $prefixToAdd = '/' . $prefixToAdd;
                    }
                    // Use the new combinePaths method here too
                    $newPrefix = $this->combinePaths($newPrefix, $prefixToAdd);
                }

                // Merge the middleware
                $newMiddleware = $this->middleware;
                if (isset($attributes['middleware'])) {
                    if (is_array($attributes['middleware'])) {
                        $newMiddleware = array_merge($newMiddleware, $attributes['middleware']);
                    } else {
                        $newMiddleware[] = $attributes['middleware'];
                    }
                }

                // Create new attributes
                $newAttributes = $attributes;
                $newAttributes['prefix'] = $newPrefix;
                $newAttributes['middleware'] = $newMiddleware;

                // Call the parent router's group method
                return $this->router->group($newAttributes, $callback);
            }
        };

        // Call the callback with the group router to collect routes
        $callback($groupRouter);

        return $this;
    }

    /**
     * Register a GET route
     *
     * @param string $path
     * @param mixed $handler
     * @return Route Route instance for method chaining
     */
    public function get(string $path, $handler): Route
    {
        $route = new Route('GET', $path, $handler, $this->middlewareRegistry);
        $this->routes[] = ['GET', $path, $handler];
        return $route;
    }

    /**
     * Register a POST route
     *
     * @param string $path
     * @param mixed $handler
     * @return Route
     */
    public function post(string $path, $handler): Route
    {
        $route = new Route('POST', $path, $handler, $this->middlewareRegistry);
        $this->routes[] = ['POST', $path, $handler];
        return $route;
    }

    /**
     * Register a PUT route
     *
     * @param string $path
     * @param mixed $handler
     * @return Route
     */
    public function put(string $path, $handler): Route
    {
        $route = new Route('PUT', $path, $handler, $this->middlewareRegistry);
        $this->routes[] = ['PUT', $path, $handler];
        return $route;
    }

    /**
     * Register a DELETE route
     *
     * @param string $path
     * @param mixed $handler
     * @return Route
     */
    public function delete(string $path, $handler): Route
    {
        $route = new Route('DELETE', $path, $handler, $this->middlewareRegistry);
        $this->routes[] = ['DELETE', $path, $handler];
        return $route;
    }

    /**
     * Register a PATCH route
     *
     * @param string $path
     * @param mixed $handler
     * @return Route
     */
    public function patch(string $path, $handler): Route
    {
        $route = new Route('PATCH', $path, $handler, $this->middlewareRegistry);
        $this->routes[] = ['PATCH', $path, $handler];
        return $route;
    }

    /**
     * Register an OPTIONS route
     *
     * @param string $path
     * @param mixed $handler
     * @return Route
     */
    public function options(string $path, $handler): Route
    {
        $route = new Route('OPTIONS', $path, $handler, $this->middlewareRegistry);
        $this->routes[] = ['OPTIONS', $path, $handler];
        return $route;
    }

    /**
     * Get middleware registry
     *
     * @return MiddlewareRegistry
     */
    public function getMiddlewareRegistry(): MiddlewareRegistry
    {
        return $this->middlewareRegistry;
    }

    /**
     * Set middleware registry
     *
     * @param MiddlewareRegistry $middlewareRegistry
     * @return self
     */
    public function setMiddlewareRegistry(MiddlewareRegistry $middlewareRegistry): self
    {
        $this->middlewareRegistry = $middlewareRegistry;
        return $this;
    }

    /**
     * Match a request to a route
     *
     * @param string $method
     * @param string $path
     * @return array
     */
    public function match(string $method, string $path): array
    {
        // Normalize the path by ensuring it starts with a slash
        if (empty($path)) {
            $path = '/';
        } elseif ($path[0] !== '/') {
            $path = '/' . $path;
        }

        // Remove trailing slash for consistency (except for root path)
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        $dispatcher = $this->createDispatcher();
        $routeInfo = $dispatcher->dispatch($method, $path);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                // Add debugging information in development mode
                if (env('APP_DEBUG', false)) {
                    $registeredRoutes = [];
                    foreach ($this->routes as $route) {
                        $registeredRoutes[] = $route[0] . ' ' . $route[1];
                    }
                    return [
                        'status' => 'not_found',
                        'debug' => [
                            'requested_method' => $method,
                            'requested_path' => $path,
                            'registered_routes' => $registeredRoutes
                        ]
                    ];
                }
                return ['status' => 'not_found'];

            case Dispatcher::METHOD_NOT_ALLOWED:
                return [
                    'status' => 'method_not_allowed',
                    'allowed_methods' => $routeInfo[1]
                ];

            case Dispatcher::FOUND:
                $handler = $routeInfo[1];

                // Try to convert string controller@method to callable
                $callable = $this->resolveController($handler);

                return [
                    'status' => 'found',
                    'handler' => $callable,
                    'originalHandler' => $handler, // Store original for reference
                    'vars' => $routeInfo[2]
                ];
        }

        return ['status' => 'error'];
    }

    /**
     * Create FastRoute dispatcher
     *
     * @return Dispatcher
     */
    private function createDispatcher()
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
                foreach ($this->routes as $route) {
                    $method = $route[0];
                    $path = $route[1];

                    // Ensure path starts with a slash for consistency
                    if (!empty($path) && $path[0] !== '/') {
                        $path = '/' . $path;
                    }

                    $r->addRoute($method, $path, $route[2]);
                }
            });
        }

        return $this->dispatcher;
    }

    /**
     * Convert string `controller@method` to callable
     *
     * @param string|callable $handler
     * @return callable
     */
    private function resolveController($handler)
    {
        // Only process string handlers in Controller@method format
        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($class, $method) = explode('@', $handler, 2);

            // If we have a container, use it to resolve the controller
            if ($this->container) {
                $controller = $this->container->make($class);
                return [$controller, $method];
            }

            // Fallback if no container: create controller instance directly
            $controller = new $class();
            return [$controller, $method];
        }

        // If it's already a callable or not in Controller@method format, return as is
        return $handler;
    }
}