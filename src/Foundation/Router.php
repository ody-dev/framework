<?php
declare(strict_types=1);

namespace Ody\Core\Foundation;

use FastRoute;
use Illuminate\Container\Container;
use Ody\Core\Foundation\Middleware\Middleware;


class Router
{
    private $dispatcher;
    private $routes = [];
    private $container;
    private $middleware;

    /**
     * Router constructor
     *
     * @param Container|null $container
     * @param Middleware|null $middleware
     */
    public function __construct($container = null, $middleware = null)
    {
        $this->container = $container;
        $this->middleware = $middleware;
    }

    /**
     * Set middleware instance
     *
     * @param Middleware $middleware
     * @return self
     */
    public function setMiddleware(Middleware $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * Get middleware instance
     *
     * @return Middleware|null
     */
    public function getMiddleware(): ?Middleware
    {
        return $this->middleware;
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
        $route = new Route('GET', $path, $handler, $this, $this->middleware);
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
        $route = new Route('POST', $path, $handler, $this, $this->middleware);
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
        $route = new Route('PUT', $path, $handler, $this, $this->middleware);
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
        $route = new Route('DELETE', $path, $handler, $this, $this->middleware);
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
        $route = new Route('PATCH', $path, $handler, $this, $this->middleware);
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
        $route = new Route('OPTIONS', $path, $handler, $this, $this->middleware);
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
                $route->middlewareList($this->middlewareList);
                return $route;
            }

            public function post($path, $handler)
            {
                $route = $this->router->post($this->prefix . $path, $handler);
                $route->middlewareList($this->middlewareList);
                return $route;
            }

            public function put($path, $handler)
            {
                $route = $this->router->put($this->prefix . $path, $handler);
                $route->middlewareList($this->middlewareList);
                return $route;
            }

            public function delete($path, $handler)
            {
                $route = $this->router->delete($this->prefix . $path, $handler);
                $route->middlewareList($this->middlewareList);
                return $route;
            }

            public function patch($path, $handler)
            {
                $route = $this->router->patch($this->prefix . $path, $handler);
                $route->middlewareList($this->middlewareList);
                return $route;
            }

            public function options($path, $handler)
            {
                $route = $this->router->options($this->prefix . $path, $handler);
                $route->middlewareList($this->middlewareList);
                return $route;
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