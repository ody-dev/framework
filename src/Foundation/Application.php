<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Core\Foundation;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Http\ResponseEmitter;
use Ody\Core\Foundation\Middleware\MiddlewareRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Main application class updated to use MiddlewareRegistry
 */
class Application
{
    /**
     * @var Router
     */
    private Router $router;

    /**
     * @var MiddlewareRegistry
     */
    private MiddlewareRegistry $middlewareRegistry;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Container
     */
    private Container $container;

    /**
     * @var ResponseEmitter
     */
    private ResponseEmitter $responseEmitter;

    /**
     * Application constructor
     *
     * @param Router|null $router
     * @param MiddlewareRegistry|null $middlewareRegistry
     * @param LoggerInterface|null $logger
     * @param Container|null $container
     * @param ResponseEmitter|null $responseEmitter
     * @throws BindingResolutionException
     */
    public function __construct(
        ?Router $router = null,
        ?MiddlewareRegistry $middlewareRegistry = null,
        ?LoggerInterface $logger = null,
        ?Container $container = null,
        ?ResponseEmitter $responseEmitter = null
    ) {
        // Initialize container
        $this->container = $container ?? new Container();

        // Register core components in container if they don't exist
        if (!$this->container->bound(Router::class) && $router === null) {
            $this->container->singleton(Router::class, function ($container) {
                return new Router($container);
            });
        }

        if (!$this->container->bound(MiddlewareRegistry::class) && $middlewareRegistry === null) {
            $this->container->singleton(MiddlewareRegistry::class, function ($container) {
                return new MiddlewareRegistry($container, $container->make(LoggerInterface::class));
            });
        }

        // Resolve core components
        $this->router = $router ?? $this->container->make(Router::class);
        $this->middlewareRegistry = $middlewareRegistry ?? $this->container->make(MiddlewareRegistry::class);
        $this->logger = $logger ?? $this->container->make(LoggerInterface::class);
        $this->responseEmitter = $responseEmitter ?? $this->container->make(ResponseEmitter::class);

        // Register self in container
        if (!$this->container->bound(Application::class)) {
            $this->container->instance(Application::class, $this);
        }
    }

    /**
     * Get router
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
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
     * Get logger
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Get container
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Handle HTTP request
     *
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     */
    public function handleRequest(?ServerRequestInterface $request = null): ResponseInterface
    {
        // Create request from globals if not provided
        $request = $request ?? Request::createFromGlobals();

        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Log incoming request
        $this->logger->info('Request received', [
            'method' => $method,
            'path' => $path,
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
        ]);

        try {
            // Find matching route
            $routeInfo = $this->router->match($method, $path);

            // Create the final handler based on route info
            $finalHandler = function (ServerRequestInterface $req) use ($routeInfo) {
                $response = new Response();

                switch ($routeInfo['status']) {
                    case 'found':
                        try {
                            // Add route parameters to request attributes
                            foreach ($routeInfo['vars'] as $key => $value) {
                                $req = $req->withAttribute($key, $value);
                            }

                            // Call the route handler with the request, response and parameters
                            $result = call_user_func(
                                $routeInfo['handler'],
                                $req,
                                $response,
                                $routeInfo['vars']
                            );

                            // If a response was returned, use that
                            if ($result instanceof ResponseInterface) {
                                return $result;
                            }

                            // If nothing was returned, return the response
                            return $response;
                        } catch (\Throwable $e) {
                            $this->logger->error('Error handling request', [
                                'message' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);

                            return $response->status(500)
                                ->json()
                                ->withJson([
                                    'error' => 'Internal Server Error'
                                ]);
                        }

                    case 'method_not_allowed':
                        $this->logger->warning('Method not allowed', [
                            'method' => $method,
                            'path' => $path,
                            'allowed_methods' => implode(', ', $routeInfo['allowed_methods'])
                        ]);

                        return $response->status(405)
                            ->header('Allow', implode(', ', $routeInfo['allowed_methods']))
                            ->json()
                            ->withJson([
                                'error' => 'Method Not Allowed'
                            ]);

                    case 'not_found':
                    default:
                        $this->logger->warning('Route not found', [
                            'method' => $method,
                            'path' => $path
                        ]);

                        return $response->status(404)
                            ->json()
                            ->withJson([
                                'error' => 'Not Found'
                            ]);
                }
            };

            // Process the request through middleware using the registry
            return $this->middlewareRegistry->process($request, $finalHandler);

        } catch (\Throwable $e) {
            // Log the error
            $this->logger->error('Unhandled exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Create an error response
            $response = new Response();
            return $response->status(500)
                ->json()
                ->withJson([
                    'error' => 'Internal Server Error'
                ]);
        }
    }

    /**
     * Run the application
     *
     * @return void
     */
    public function run(): void
    {
        $response = $this->handleRequest();
        $this->responseEmitter->emit($response);
    }
}