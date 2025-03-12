<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation;

use Ody\Container\Container;
use Ody\Container\Contracts\BindingResolutionException;
use Ody\Foundation\Http\Request;
use Ody\Foundation\Http\Response;
use Ody\Foundation\Http\ResponseEmitter;
use Ody\Foundation\Logging\NullLogger;
use Ody\Foundation\Middleware\MiddlewareRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use function PHPUnit\Framework\throwException;

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

        // If LoggerInterface wasn't provided but we're expected to use it,
        // create a default NullLogger instead of trying to resolve it
        $this->logger = $logger ?? ($this->container->has(LoggerInterface::class)
            ? $this->container->make(LoggerInterface::class)
            : new NullLogger());

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
     * @throws \Exception
     */
    public function handleRequest(?ServerRequestInterface $request = null): ResponseInterface
    {
        try {
            // Create request from globals if not provided
            $request = $request ?? Request::createFromGlobals();

            // Log incoming request
            $this->logRequest($request);

            // Find matching route
            $routeInfo = $this->router->match(
                $request->getMethod(),
                $request->getUri()->getPath()
            );

            // Create the final handler based on route info
            $finalHandler = $this->createRouteHandler($routeInfo);

            // Process the request through middleware using the registry
            return $this->middlewareRegistry->process($request, $finalHandler);
        } catch (\Throwable $e) {
            throw new \Exception($e);
            return $this->handleException($e);
        }
    }

    /**
     * Log the incoming request details
     *
     * @param ServerRequestInterface $request
     * @return void
     */
    private function logRequest(ServerRequestInterface $request): void
    {
        $this->logger->info('Request received', [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    /**
     * Create a handler function for the matched route
     *
     * @param array $routeInfo
     * @return callable
     */
    private function createRouteHandler(array $routeInfo): callable
    {
        return function (ServerRequestInterface $request) use ($routeInfo) {
            $response = new Response();

            match($routeInfo['status']) {
                'found' => $this->handleFoundRoute($request, $response, $routeInfo),
                'method_not_allowed' => $this->handleMethodNotAllowed($response, $request, $routeInfo),
                default => $this->handleNotFound($response, $request) // not found
            };
        };
    }

    /**
     * Handle a found route
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $routeInfo
     * @return ResponseInterface
     */
    private function handleFoundRoute(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $routeInfo
    ): ResponseInterface
    {
        try {
            // Add route parameters to request attributes
            foreach ($routeInfo['vars'] as $key => $value) {
                $request = $request->withAttribute($key, $value);
            }

            // Call the route handler with the request, response and parameters
            $result = call_user_func(
                $routeInfo['handler'],
                $request,
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
            $this->logException($e, 'Error handling request');
            return $this->createErrorResponse($response, 500, 'Internal Server Error');
        }
    }

    /**
     * Handle method not allowed response
     *
     * @param ResponseInterface $response
     * @param ServerRequestInterface $request
     * @param array $routeInfo
     * @return ResponseInterface
     */
    private function handleMethodNotAllowed(
        ResponseInterface $response,
        ServerRequestInterface $request,
        array $routeInfo
    ): ResponseInterface
    {
        $allowedMethods = implode(', ', $routeInfo['allowed_methods']);

        $this->logger->warning('Method not allowed', [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'allowed_methods' => $allowedMethods
        ]);

        return $response->status(405)
            ->header('Allow', $allowedMethods)
            ->json()
            ->withJson([
                'error' => 'Method Not Allowed'
            ]);
    }

    /**
     * Handle not found response
     *
     * @param ResponseInterface $response
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function handleNotFound(
        ResponseInterface $response,
        ServerRequestInterface $request
    ): ResponseInterface
    {
        $this->logger->warning('Route not found', [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath()
        ]);

        return $response->status(404)
            ->json()
            ->withJson([
                'error' => 'Not Found'
            ]);
    }

    /**
     * Handle unhandled exceptions
     *
     * @param \Throwable $e
     * @return ResponseInterface
     */
    private function handleException(\Throwable $e): ResponseInterface
    {
        $this->logException($e, 'Unhandled exception', true);

        $response = new Response();
        return $this->createErrorResponse($response, 500, 'Internal Server Error');
    }

    /**
     * Log exception details
     *
     * @param \Throwable $e
     * @param string $message
     * @param bool $includeTrace
     * @return void
     */
    private function logException(\Throwable $e, string $message, bool $includeTrace = false): void
    {
        $logData = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];

        if ($includeTrace) {
            $logData['trace'] = $e->getTraceAsString();
        }

        $this->logger->error($message, $logData);
    }

    /**
     * Create a JSON error response
     *
     * @param ResponseInterface $response
     * @param int $status
     * @param string $message
     * @return ResponseInterface
     */
    private function createErrorResponse(
        ResponseInterface $response,
        int $status,
        string $message
    ): ResponseInterface
    {
        return $response->status($status)
            ->json()
            ->withJson([
                'error' => $message
            ]);
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