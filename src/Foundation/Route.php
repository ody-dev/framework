<?php

namespace Ody\Core\Foundation;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Middleware\Middleware;
use Psr\Http\Server\RequestHandlerInterface;
use Ody\Core\Foundation\Http\RequestHandler;

/**
 * Route class for defining application routes
 */
class Route
{
    /**
     * @var string HTTP method
     */
    private string $method;

    /**
     * @var string Route path
     */
    private string $path;

    /**
     * @var mixed Route handler
     */
    private $handler;

    /**
     * @var Middleware
     */
    private Middleware $middleware;

    /**
     * Route constructor
     *
     * @param string $method
     * @param string $path
     * @param mixed $handler
     * @param Middleware $middleware
     */
    public function __construct(string $method, string $path, $handler, Middleware $middleware)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
        $this->middleware = $middleware;
    }

    /**
     * Add middleware to the route
     *
     * @param string|callable $middleware
     * @return self
     */
    public function middleware($middleware): self
    {
        // Check if this is a parameterized middleware (e.g., 'role:admin')
        if (is_string($middleware) && strpos($middleware, ':') !== false) {
            [$name, $parameter] = explode(':', $middleware, 2);

            // Create a wrapper middleware that adds the parameter to the request
            $this->middleware->addToRoute($this->method, $this->path, function (ServerRequestInterface $request, callable $next) use ($name, $parameter) {
                // Add parameter to request for the named middleware to use
                if ($request instanceof Request) {
                    $request->middlewareParams = $request->middlewareParams ?? [];
                    $request->middlewareParams[$name] = $parameter;
                }

                // Get the actual middleware
                $namedMiddleware = $this->middleware->getNamedMiddleware($name);

                if ($namedMiddleware) {
                    // Create a PSR-15 compatible request handler for the next middleware
                    $handler = new class($next) implements RequestHandlerInterface {
                        private $next;

                        public function __construct(callable $next) {
                            $this->next = $next;
                        }

                        public function handle(ServerRequestInterface $request): ResponseInterface {
                            return call_user_func($this->next, $request);
                        }
                    };

                    // Call the middleware with the request and next handler
                    return $namedMiddleware($request, $next);
                }

                // If the middleware wasn't found, just continue to the next one
                return $next($request);
            });
        } else {
            // Regular middleware (no parameters)
            $this->middleware->addToRoute($this->method, $this->path, $middleware);
        }

        return $this;
    }

    /**
     * Get the route method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the route path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the route handler
     *
     * @return mixed
     */
    public function getHandler()
    {
        return $this->handler;
    }
}