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
use Ody\Container\Container;
use Ody\Foundation\Middleware\MiddlewareRegistry;

class Router
{
    /**
     * @var FastRoute\Dispatcher|null
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
        ?Container $container = null,
        ?MiddlewareRegistry $middlewareRegistry = null
    ) {
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
     * Get middleware registry
     *
     * @return MiddlewareRegistry
     */
    public function getMiddlewareRegistry(): MiddlewareRegistry
    {
        return $this->middlewareRegistry;
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
     * Create a route group with shared attributes
     *
     * @param array $attributes Group attributes (prefix, middleware)
     * @param callable $callback Function to define routes in the group
     * @return self
     */
    public function group(array $attributes, callable $callback): self
    {
        $prefix = $attributes['prefix'] ?? '';
        $middlewareList = $attributes['middleware'] ?? [];

        // Create a router proxy to capture routes in the group
        $groupRouter = new class($this, $prefix, $middlewareList) {
            private $router;
            private $prefix;
            private $middlewareList;

            public function __construct($router, $prefix, $middlewareList)
            {
                $this->router = $router;
                $this->prefix = $prefix;
                $this->middlewareList = $middlewareList;
            }

            public function get($path, $handler)
            {
                $route = $this->router->get($this->prefix . $path, $handler);

                // Apply middleware from the group to the route
                foreach ($this->middlewareList as $middleware) {
                    $route->middleware($middleware);
                }

                return $route;
            }

            public function post($path, $handler)
            {
                $route = $this->router->post($this->prefix . $path, $handler);

                foreach ($this->middlewareList as $middleware) {
                    $route->middleware($middleware);
                }

                return $route;
            }

            public function put($path, $handler)
            {
                $route = $this->router->put($this->prefix . $path, $handler);

                foreach ($this->middlewareList as $middleware) {
                    $route->middleware($middleware);
                }

                return $route;
            }

            public function delete($path, $handler)
            {
                $route = $this->router->delete($this->prefix . $path, $handler);

                foreach ($this->middlewareList as $middleware) {
                    $route->middleware($middleware);
                }

                return $route;
            }

            public function patch($path, $handler)
            {
                $route = $this->router->patch($this->prefix . $path, $handler);

                foreach ($this->middlewareList as $middleware) {
                    $route->middleware($middleware);
                }

                return $route;
            }

            public function options($path, $handler)
            {
                $route = $this->router->options($this->prefix . $path, $handler);

                foreach ($this->middlewareList as $middleware) {
                    $route->middleware($middleware);
                }

                return $route;
            }

            // Add pattern middleware for all routes in the group
            public function __destruct()
            {
                // If the group has a pattern-based prefix, add pattern middleware
                if (strpos($this->prefix, '*') !== false) {
                    $registry = $this->router->getMiddlewareRegistry();

                    foreach ($this->middlewareList as $middleware) {
                        $registry->addToPattern('*:' . $this->prefix . '*', $middleware);
                    }
                }
            }
        };

        // Call the callback with the group router
        $callback($groupRouter);

        return $this;
    }

    /**
     * Create FastRoute dispatcher
     *
     * @return FastRoute\Dispatcher
     */
    private function createDispatcher()
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) {
                foreach ($this->routes as $route) {
                    $r->addRoute($route[0], $route[1], $route[2]);
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

    /**
     * Match a request to a route
     *
     * @param string $method
     * @param string $path
     * @return array
     */
    public function match(string $method, string $path): array
    {
        $dispatcher = $this->createDispatcher();
        $routeInfo = $dispatcher->dispatch($method, $path);

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                return ['status' => 'not_found'];

            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                return [
                    'status' => 'method_not_allowed',
                    'allowed_methods' => $routeInfo[1]
                ];

            case \FastRoute\Dispatcher::FOUND:
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
}