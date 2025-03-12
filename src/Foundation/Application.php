<?php
// This is a partial update of the Application class to show how to use the ResponseEmitter

namespace Ody\Core\Foundation;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Http\ResponseEmitter;
use Ody\Core\Foundation\Http\Swoole\SwooleResponseEmitter;
use Ody\Core\Foundation\Middleware\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class Application
{
    /**
     * @var ResponseEmitter
     */
    private ResponseEmitter $responseEmitter;

    /**
     * Application constructor with ResponseEmitter
     *
     * @param Router|null $router
     * @param Middleware|null $middleware
     * @param LoggerInterface|null $logger
     * @param Container|null $container
     * @param ResponseEmitter|null $responseEmitter
     */
    public function __construct(
        ?Router $router = null,
        ?Middleware $middleware = null,
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

        if (!$this->container->bound(Middleware::class) && $middleware === null) {
            $this->container->singleton(Middleware::class, function ($container) {
                return new Middleware($container);
            });
        }

        // Resolve core components
        $this->router = $router ?? $this->container->make(Router::class);
        $this->middleware = $middleware ?? $this->container->make(Middleware::class);
        $this->logger = $logger ?? $this->container->make(LoggerInterface::class);

        // Create the appropriate ResponseEmitter based on environment
        if (!$responseEmitter) {
            // TODO: change extension_loaded to isRunningOnSwooleHttp
            if (extension_loaded('swoole') && false) {
                $responseEmitter = new SwooleResponseEmitter($this->logger);
            } else {
                $responseEmitter = new ResponseEmitter($this->logger);
            }
        }
        $this->responseEmitter = $responseEmitter;

        // Register the emitter in the container
        $this->container->instance(ResponseEmitter::class, $this->responseEmitter);

        // Register self in container
        if (!$this->container->bound(Application::class)) {
            $this->container->instance(Application::class, $this);
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
        $this->emitResponse($response);
    }

    /**
     * Emit the response
     *
     * @param ResponseInterface $response
     * @return void
     */
    public function emitResponse(ResponseInterface $response): void
    {
        $this->responseEmitter->emit($response);
    }

    /**
     * Get the response emitter
     *
     * @return ResponseEmitter
     */
    public function getResponseEmitter(): ResponseEmitter
    {
        return $this->responseEmitter;
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
     * Get middleware
     *
     * @return Middleware
     */
    public function getMiddleware(): Middleware
    {
        return $this->middleware;
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

        // Find matching route
        $routeInfo = $this->router->match($method, $path);

        // Create a handler for the request based on route info
        $handler = function (ServerRequestInterface $req) use ($routeInfo, $method, $path, $request): ResponseInterface {
            $response = new Response();

            switch ($routeInfo['status']) {
                case 'found':
                    try {
                        // Add route parameters to request attributes
                        foreach ($routeInfo['vars'] as $key => $value) {
                            $req = $req->withAttribute($key, $value);
                        }

                        // If request is our custom Request class, also set routeParams for backward compatibility
                        if ($req instanceof Request) {
                            $req->routeParams = $routeInfo['vars'];
                        }

                        // The handler should now be callable at this point
                        $handler = $routeInfo['handler'];

                        // Call the route handler with the PSR-7 request and response
                        $result = call_user_func($handler, $req, $response, $routeInfo['vars']);

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

        // Process the request through the middleware
        $response = $this->middleware->run($request, $handler);

        // Ensure PSR-7 response is returned
        return $response;
    }

    /**
     * Send a PSR-7 response
     *
     * @param ResponseInterface $response
     * @return void
     */
    private function sendPsr7Response(ResponseInterface $response): void
    {
        // Set status code
        http_response_code($response->getStatusCode());

        // Set headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        // Output body
        echo (string) $response->getBody();
    }
}