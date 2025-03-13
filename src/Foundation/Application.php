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
use Ody\Foundation\Http\Request;
use Ody\Foundation\Http\Response;
use Ody\Foundation\Http\ResponseEmitter;
use Ody\Foundation\Logging\LogManager;
use Ody\Foundation\Middleware\MiddlewareRegistry;
use Ody\Foundation\Providers\ServiceProviderManager;
use Ody\Foundation\Support\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Main application class with integrated bootstrapping
 */
class Application
{
    /**
     * @var Router|null
     */
    private ?Router $router = null;

    /**
     * @var MiddlewareRegistry|null
     */
    private ?MiddlewareRegistry $middlewareRegistry = null;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Container
     */
    private Container $container;

    /**
     * @var ResponseEmitter|null
     */
    private ?ResponseEmitter $responseEmitter = null;

    /**
     * @var ServiceProviderManager
     */
    private ServiceProviderManager $providerManager;

    // Add these properties to the class
    /**
     * @var bool Indicates if the application is running in console
     */
    private bool $runningInConsole = false;

    /**
     * @var bool Indicates if console detection has been performed
     */
    private bool $consoleDetected = false;

    /**
     * @var bool Whether the application has been bootstrapped
     */
    private bool $bootstrapped = false;

    /**
     * Core providers that must be registered in a specific order
     *
     * @var array|string[]
     */
    private array $providers = [
        \Ody\Foundation\Providers\EnvServiceProvider::class,
        \Ody\Foundation\Providers\ConfigServiceProvider::class,
        \Ody\Foundation\Providers\LoggingServiceProvider::class,
        \Ody\Foundation\Providers\ApplicationServiceProvider::class,
        \Ody\Foundation\Providers\FacadeServiceProvider::class,
        \Ody\Foundation\Providers\MiddlewareServiceProvider::class,
        \Ody\Foundation\Providers\RouteServiceProvider::class,
    ];

    /**
     * Application constructor with reduced dependencies
     *
     * @param Container $container
     * @param ServiceProviderManager $providerManager
     */
    public function __construct(
        Container $container,
        ServiceProviderManager $providerManager
    ) {
        // Store essential components
        $this->container = $container;
        $this->providerManager = $providerManager;

        // Use a NullLogger temporarily until a real logger is registered
        $this->logger = $container->has(LoggerInterface::class)
            ? $container->make(LoggerInterface::class)
            : new NullLogger();

        // Register self in container
        if (!$this->container->bound(Application::class)) {
            $this->container->instance(Application::class, $this);
        }
        if (!$this->container->bound('app')) {
            $this->container->alias(Application::class, 'app');
        }
    }

    /**
     * Bootstrap the application by loading providers
     *
     * @return self
     */
    public function bootstrap(): self
    {
        if ($this->bootstrapped) {
            return $this;
        }

        // Load core service providers
        $this->registerCoreProviders();

        // Register providers from configuration
        $this->providerManager->registerConfigProviders('app.providers');

        // Boot all registered providers
        $this->providerManager->boot();

        // Initialize core components lazily (only created when first accessed)
        $this->initializeCoreComponents();

        $this->bootstrapped = true;
        return $this;
    }

    /**
     * Register core framework service providers
     *
     * @return void
     */
    protected function registerCoreProviders(): void
    {
        foreach ($this->providers as $provider) {
            // Only register if class exists (allows for optional components)
            if (class_exists($provider)) {
                $this->providerManager->register($provider);
            }
        }
    }

    /**
     * Initialize core components lazily using container callbacks
     *
     * @return void
     */
    protected function initializeCoreComponents(): void
    {
        // Update logger reference after provider registration
        if ($this->container->has(LoggerInterface::class)) {
            $this->logger = $this->container->make(LoggerInterface::class);
        } else if ($this->container->has(LogManager::class)) {
            $this->logger = $this->container->make(LogManager::class)->channel();
            $this->container->instance(LoggerInterface::class, $this->logger);
        }

        // Register ResponseEmitter if not already registered
        if (!$this->container->has(ResponseEmitter::class)) {
            $this->container->singleton(ResponseEmitter::class, function ($container) {
                return new ResponseEmitter(
                    $container->make(LoggerInterface::class),
                    true,
                    8192
                );
            });
        }
    }

    /**
     * Get router (lazy-loaded)
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        if ($this->router === null) {
            $this->router = $this->container->make(Router::class);
        }

        return $this->router;
    }

    /**
     * Get middleware registry (lazy-loaded)
     *
     * @return MiddlewareRegistry
     */
    public function getMiddlewareRegistry(): MiddlewareRegistry
    {
        if ($this->middlewareRegistry === null) {
            $this->middlewareRegistry = $this->container->make(MiddlewareRegistry::class);
        }

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
     * Get response emitter (lazy-loaded)
     *
     * @return ResponseEmitter
     */
    public function getResponseEmitter(): ResponseEmitter
    {
        if ($this->responseEmitter === null) {
            $this->responseEmitter = $this->container->make(ResponseEmitter::class);
        }

        return $this->responseEmitter;
    }

    /**
     * Handle HTTP request
     *
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     */
    public function handleRequest(?ServerRequestInterface $request = null): ResponseInterface
    {
            // Make sure application is bootstrapped
            if (!$this->bootstrapped) {
                $this->bootstrap();
            }

            // Create request from globals if not provided
            $request = $request ?? Request::createFromGlobals();

            // Log incoming request
            $this->logRequest($request);

            // Find matching route
            $routeInfo = $this->getRouter()->match(
                $request->getMethod(),
                $request->getUri()->getPath()
            );

            // Create the final handler based on route info
            $finalHandler = $this->createRouteHandler($routeInfo);

            // Process the request through middleware using the registry
            return $this->getMiddlewareRegistry()->process($request, $finalHandler);
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

            return match($routeInfo['status']) {
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

        return $response->withStatus(405)
            ->withHeader('Allow', $allowedMethods)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->createJsonBody([
                'error' => 'Method Not Allowed'
            ]));
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

        return $response->withStatus(404)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->createJsonBody([
                'error' => 'Not Found'
            ]));
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
        return $response->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->createJsonBody([
                'error' => $message
            ]));
    }

    /**
     * Create a JSON response body
     *
     * @param array $data
     * @return \Psr\Http\Message\StreamInterface
     */
    private function createJsonBody(array $data): \Psr\Http\Message\StreamInterface
    {
        $factory = $this->container->make('Psr\Http\Message\StreamFactoryInterface');
        return $factory->createStream(json_encode($data));
    }

    /**
     * Run the application
     *
     * @return void
     */
    public function run(): void
    {
        // Ensure application is bootstrapped
        if (!$this->bootstrapped) {
            $this->bootstrap();
        }

        $response = $this->handleRequest();
        $this->getResponseEmitter()->emit($response);
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole(): bool
    {
        if ($this->consoleDetected) {
            return $this->runningInConsole;
        }

        // Check if explicitly set
        if ($this->runningInConsole) {
            $this->consoleDetected = true;
            return true;
        }

        // Check if running via CLI SAPI
        if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            $this->runningInConsole = true;
            $this->consoleDetected = true;
            return true;
        }

        // Check for specific environment variables for CLI tools
        $this->runningInConsole = (bool) (getenv('CONSOLE_MODE') ||
            getenv('APP_RUNNING_IN_CONSOLE') ||
            (isset($_ENV['APP_RUNNING_IN_CONSOLE']) && $_ENV['APP_RUNNING_IN_CONSOLE']));
        $this->consoleDetected = true;

        return $this->runningInConsole;
    }

    /**
     * Alias for runningInConsole() - shorter method name for convenience
     *
     * @return bool
     */
    public function isConsole(): bool
    {
        return $this->runningInConsole();
    }

    /**
     * Set the running in console status.
     *
     * @param bool $runningInConsole
     * @return self
     */
    public function setRunningInConsole(bool $runningInConsole): self
    {
        $this->runningInConsole = $runningInConsole;

        // Also set the environment variable for broader access
        putenv('APP_RUNNING_IN_CONSOLE=' . ($runningInConsole ? '1' : '0'));

        return $this;
    }

    /**
     * Get the ServiceProviderManager instance
     *
     * @return ServiceProviderManager
     */
    public function getProviderManager(): ServiceProviderManager
    {
        return $this->providerManager;
    }
}