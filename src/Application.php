<?php
declare(strict_types=1);
namespace Ody\Core;

use Illuminate\Container\Container;
use Ody\Core\Middleware\Middleware;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class Application
{
    private Router $router;
    private Middleware $middleware;
    private Logger $logger;
    private Server $server;
    private Container $container;

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

        // Initialize Swoole HTTP Server
        $this->server = new Server('0.0.0.0', 9501);
        $this->server->set([
            'worker_num' => 4,
            'max_request' => 10000,
            'daemonize' => false,
        ]);

        $this->server->on('request', [$this, 'handleRequest']);
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getMiddleware(): Middleware
    {
        return $this->middleware;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function handleRequest(Request $request, Response $response): void
    {
        $method = $request->server['request_method'] ?? 'GET';
        $path = $request->server['request_uri'] ?? '/';

        // Log incoming request
        $this->logger->info('Request received', [
            'method' => $method,
            'path' => $path,
            'ip' => $request->server['remote_addr'] ?? 'unknown'
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
                    $this->middleware->run($request, $response, function (Request $req, Response $res) use ($handler, $routeInfo) {
                        return call_user_func($handler, $req, $res, $routeInfo['vars']);
                    });
                } catch (\Throwable $e) {
                    $this->logger->error('Error handling request', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);

                    $response->status(500);
                    $response->header('Content-Type', 'application/json');
                    $response->end(json_encode([
                        'error' => 'Internal Server Error'
                    ]));
                }
                break;

            case 'method_not_allowed':
                $this->logger->warning('Method not allowed', [
                    'method' => $method,
                    'path' => $path,
                    'allowed_methods' => implode(', ', $routeInfo['allowed_methods'])
                ]);

                $response->status(405);
                $response->header('Allow', implode(', ', $routeInfo['allowed_methods']));
                $response->header('Content-Type', 'application/json');
                $response->end(json_encode([
                    'error' => 'Method Not Allowed'
                ]));
                break;

            case 'not_found':
            default:
                $this->logger->warning('Route not found', [
                    'method' => $method,
                    'path' => $path
                ]);

                $response->status(404);
                $response->header('Content-Type', 'application/json');
                $response->end(json_encode([
                    'error' => 'Not Found'
                ]));
                break;
        }
    }

    public function start(): void
    {
        $this->logger->info('Server starting on http://0.0.0.0:9501');
        $this->server->start();
    }
}