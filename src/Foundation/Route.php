<?php
namespace Ody\Core\Foundation;

use Ody\Core\Foundation\Middleware\Middleware;

/**
 * Route class for fluent middleware definition
 */
class Route
{
    /**
     * @var string HTTP method
     */
    private $method;

    /**
     * @var string Route path
     */
    private $path;

    /**
     * @var mixed Route handler
     */
    private $handler;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var Middleware
     */
    private $middleware;

    /**
     * Route constructor
     *
     * @param string $method
     * @param string $path
     * @param mixed $handler
     * @param Router $router
     * @param Middleware $middleware
     */
    public function __construct(string $method, string $path, $handler, Router $router, Middleware $middleware)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
        $this->router = $router;
        $this->middleware = $middleware;
    }

    /**
     * Apply middleware to this route
     *
     * @param string|callable $middleware Middleware name(s) or callable
     * @return self
     */
    public function middleware($middleware): self
    {
        // Handle middleware with parameters (auth:api)
        if (is_string($middleware) && strpos($middleware, ':') !== false) {
            list($name, $parameter) = explode(':', $middleware, 2);

            // Create a wrapper middleware that adds the parameter to the request
            $this->middleware->addToRoute($this->method, $this->path, function ($request, $response, $next) use ($name, $parameter) {
                // Add parameter to request for the named middleware to use
                $request->middlewareParams = $request->middlewareParams ?? [];
                $request->middlewareParams[$name] = $parameter;

                // Get the actual middleware and execute it
                $namedMiddleware = $this->middleware->getNamedMiddleware($name);
                return $namedMiddleware($request, $response, $next);
            });
        } else {
            // Standard middleware
            $this->middleware->addToRoute($this->method, $this->path, $middleware);
        }

        return $this;
    }

    /**
     * Apply multiple middleware to this route
     *
     * @param array $middlewareList Array of middleware names or callables
     * @return self
     */
    public function middlewareList(array $middlewareList): self
    {
        foreach ($middlewareList as $middleware) {
            $this->middleware($middleware);
        }

        return $this;
    }

    /**
     * Get HTTP method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get route path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get route handler
     *
     * @return mixed
     */
    public function getHandler()
    {
        return $this->handler;
    }
}