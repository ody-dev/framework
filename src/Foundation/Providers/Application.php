<?php

namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Logger;
use Ody\Core\Foundation\Middleware\Middleware;
use Ody\Core\Router;

/**
 * Main application class
 */
class Application
{
    /**
     * @var Router
     */
    private $router;

    /**
     * @var Middleware
     */
    private $middleware;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Container
     */
    private $container;

    /**
     * Application constructor
     *
     * @param Router|null $router
     * @param Middleware|null $middleware
     * @param Logger|null $logger
     * @param Container|null $container
     */
    public function __construct(
        ?Router $router = null,
        ?Middleware $middleware = null,
        ?Logger $logger = null,
        ?Container $container = null
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
            $this->container->singleton(Middleware::class, function () {
                return new Middleware();
            });
        }

        if (!$this->container->bound(Logger::class) && $logger === null) {
            $this->container->singleton(Logger::class, function () {
                return new Logger();
            });
        }

        // Resolve core components
        $this->router = $router ?? $this->container->make(Router::class);
        $this->middleware = $middleware ?? $this->container->make(Middleware::class);
        $this->logger = $logger ?? $this->container->make(Logger::class);

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
     * @return Logger
     */
    public function getLogger(): Logger
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
     * @param Request|null $request
     * @return Response
     */
    public function handleRequest(?Request $request = null): Response
    {
        // Create request from globals if not provided
        $request = $request ?? Request::createFromGlobals();
        $response = new Response();

        $method = $request->getMethod();
        $path = $request->getPath();

        // Log incoming request
        $this->logger->info('Request received', [
            'method' => $method,
            'path' => $path,
            'ip' => $request->server['REMOTE_ADDR'] ?? 'unknown'
        ]);

        // Find matching route
        $routeInfo = $this->router->match($method, $path);

        switch ($routeInfo['status']) {
            case 'found':
                try {
                    // Add route parameters to request for middleware access
                    $request->routeParams = $routeInfo['vars'];

                    // The handler should now be callable at this point
                    $handler = $routeInfo['handler'];

                    // Run middleware stack and route handler
                    $this->middleware->run($request, $response, function ($req, $res) use ($handler, $routeInfo) {
                        return call_user_func($handler, $req, $res, $routeInfo['vars']);
                    });
                } catch (\Throwable $e) {
                    $this->logger->error('Error handling request', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);

                    $response->status(500)
                        ->json()
                        ->withJson([
                            'error' => 'Internal Server Error'
                        ]);
                }
                break;

            case 'method_not_allowed':
                $this->logger->warning('Method not allowed', [
                    'method' => $method,
                    'path' => $path,
                    'allowed_methods' => implode(', ', $routeInfo['allowed_methods'])
                ]);

                $response->status(405)
                    ->header('Allow', implode(', ', $routeInfo['allowed_methods']))
                    ->json()
                    ->withJson([
                        'error' => 'Method Not Allowed'
                    ]);
                break;

            case 'not_found':
            default:
                $this->logger->warning('Route not found', [
                    'method' => $method,
                    'path' => $path
                ]);

                $response->status(404)
                    ->json()
                    ->withJson([
                        'error' => 'Not Found'
                    ]);
                break;
        }

        // Ensure response is sent
        if (!$response->isSent()) {
            $response->send();
        }

        return $response;
    }

    /**
     * Run the application
     *
     * @return void
     */
    public function run(): void
    {
        $this->handleRequest();
    }
}