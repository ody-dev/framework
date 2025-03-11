<?php

namespace Ody\Core\Middleware;

use Swoole\HTTP\Request;
use Swoole\HTTP\Response;

/**
 * Middleware handler
 */
class Middleware
{
    /**
     * @var array
     */
    private $globalMiddleware = [];

    /**
     * @var array
     */
    private $namedMiddleware = [];

    /**
     * @var array
     */
    private $routeMiddleware = [];

    /**
     * @var array
     */
    private $groups = [];

    /**
     * @var RouteMiddlewareManager|null
     */
    private $routeMiddlewareManager;

    /**
     * Middleware constructor
     *
     * @param RouteMiddlewareManager|null $routeMiddlewareManager
     */
    public function __construct(?RouteMiddlewareManager $routeMiddlewareManager = null)
    {
        $this->routeMiddlewareManager = $routeMiddlewareManager;
    }

    /**
     * Add global middleware
     *
     * @param callable $middleware
     * @return self
     */
    public function add(callable $middleware): self
    {
        $this->globalMiddleware[] = $middleware;

        if ($this->routeMiddlewareManager) {
            $this->routeMiddlewareManager->addGlobal($middleware);
        }

        return $this;
    }

    /**
     * Register a named middleware
     *
     * @param string $name
     * @param callable $middleware
     * @return self
     */
    public function addNamed(string $name, callable $middleware): self
    {
        $this->namedMiddleware[$name] = $middleware;

        if ($this->routeMiddlewareManager) {
            $this->routeMiddlewareManager->addNamed($name, $middleware);
        }

        return $this;
    }

    /**
     * Get a named middleware
     *
     * @param string $name
     * @return callable|null
     */
    public function getNamedMiddleware(string $name): ?callable
    {
        return $this->namedMiddleware[$name] ?? null;
    }

    /**
     * Apply middleware to a specific route
     *
     * @param string $method HTTP method
     * @param string $path Route path
     * @param string|callable $middleware Middleware name or callable
     * @return self
     */
    public function addToRoute(string $method, string $path, $middleware): self
    {
        $route = $this->formatRoute($method, $path);

        if (!isset($this->routeMiddleware[$route])) {
            $this->routeMiddleware[$route] = [];
        }

        $this->routeMiddleware[$route][] = $middleware;

        if ($this->routeMiddlewareManager) {
            $this->routeMiddlewareManager->addToRoute($method, $path, $middleware);
        }

        return $this;
    }

    /**
     * Apply middleware to multiple routes using a pattern
     *
     * @param string $pattern Route pattern (uses fnmatch)
     * @param string|callable $middleware Middleware name or callable
     * @return self
     */
    public function addToGroup(string $pattern, $middleware): self
    {
        $this->groups[] = [
            'pattern' => $pattern,
            'middleware' => $middleware
        ];

        if ($this->routeMiddlewareManager) {
            $this->routeMiddlewareManager->addToGroup($pattern, $middleware);
        }

        return $this;
    }

    /**
     * Format route identifier
     *
     * @param string $method
     * @param string $path
     * @return string
     */
    private function formatRoute(string $method, string $path): string
    {
        return strtoupper($method) . ':' . $path;
    }

    /**
     * Check if route matches a pattern
     *
     * @param string $route
     * @param string $pattern
     * @return bool
     */
    private function routeMatchesPattern(string $route, string $pattern): bool
    {
        return fnmatch($pattern, $route);
    }

    /**
     * Get middleware for a specific route
     *
     * @param string $method
     * @param string $path
     * @return array
     */
    public function getMiddlewareForRoute(string $method, string $path): array
    {
        $route = $this->formatRoute($method, $path);
        $middleware = $this->globalMiddleware;

        // Add route-specific middleware
        if (isset($this->routeMiddleware[$route])) {
            foreach ($this->routeMiddleware[$route] as $m) {
                $middleware[] = $this->resolveMiddleware($m);
            }
        }

        // Add group middleware
        foreach ($this->groups as $group) {
            if ($this->routeMatchesPattern($route, $group['pattern'])) {
                $middleware[] = $this->resolveMiddleware($group['middleware']);
            }
        }

        return $middleware;
    }

    /**
     * Resolve middleware from name or callable
     *
     * @param string|callable $middleware
     * @return callable
     * @throws \InvalidArgumentException
     */
    private function resolveMiddleware($middleware): callable
    {
        if (is_callable($middleware)) {
            return $middleware;
        }

        if (is_string($middleware) && isset($this->namedMiddleware[$middleware])) {
            return $this->namedMiddleware[$middleware];
        }

        throw new \InvalidArgumentException("Middleware '$middleware' not found");
    }

    /**
     * Run middleware stack for a route
     *
     * @param Request $request
     * @param Response $response
     * @param callable $handler
     * @return mixed
     */
    public function run(Request $request, Response $response, callable $handler)
    {
        $method = $request->server['request_method'] ?? 'GET';
        $path = $request->server['request_uri'] ?? '/';

        if ($this->routeMiddlewareManager) {
            return $this->routeMiddlewareManager->run($request, $response, $handler, $method, $path);
        }

        $middleware = $this->getMiddlewareForRoute($method, $path);
        $next = $handler;

        // Execute middleware in reverse order
        $middlewareStack = array_reverse($middleware);

        foreach ($middlewareStack as $m) {
            $next = function ($request, $response) use ($m, $next) {
                return $m($request, $response, $next);
            };
        }

        return $next($request, $response);
    }
}