<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Middleware\Middleware;
use Ody\Core\Foundation\Middleware\CorsMiddleware;
use Ody\Core\Foundation\Middleware\JsonBodyParserMiddleware;
use Ody\Core\Foundation\Middleware\LoggingMiddleware;
use Ody\Core\Foundation\Middleware\AuthMiddleware;
use Ody\Core\Foundation\Middleware\RoleMiddleware;
use Ody\Core\Foundation\Middleware\ThrottleMiddleware;
use Ody\Core\Foundation\Support\Config;
use Psr\Log\LoggerInterface;

/**
 * Service provider for middleware
 */
class MiddlewareServiceProvider extends AbstractServiceProvider
{
    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Register Middleware
        $this->container->singleton(Middleware::class, function ($container) {
            return new Middleware($container);
        });

        // Register PSR-15 middleware implementations
        $this->container->singleton(CorsMiddleware::class, function ($container) {
            $config = $container->make(Config::class);
            $corsConfig = $config->get('app.cors', []);

            return new CorsMiddleware($corsConfig);
        });

        $this->container->singleton(JsonBodyParserMiddleware::class, function () {
            return new JsonBodyParserMiddleware();
        });

        $this->container->singleton(LoggingMiddleware::class, function ($container) {
            $logger = $container->make(LoggerInterface::class);
            return new LoggingMiddleware($logger);
        });
    }

    /**
     * Bootstrap middleware
     *
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        $middleware = $container->make(Middleware::class);
        $logger = $container->make(LoggerInterface::class);
        $config = $container->make(Config::class);

        // Register named middleware
        $this->registerNamedMiddleware($middleware, $logger);

        // Register global middleware from configuration
        $this->registerGlobalMiddleware($middleware, $container, $config);
    }

    /**
     * Register named middleware for use in routes
     *
     * @param Middleware $middleware
     * @param LoggerInterface $logger
     * @return void
     */
    protected function registerNamedMiddleware(Middleware $middleware, LoggerInterface $logger): void
    {
        // Register auth middleware
        $middleware->addNamed('auth', function (Request $request, callable $next) use ($logger) {
            // Create auth middleware with 'web' guard
            $authMiddleware = new AuthMiddleware('web', $logger);

            // Process the request
            return $authMiddleware->process($request, new class ($next) implements \Psr\Http\Server\RequestHandlerInterface {
                private $next;

                public function __construct(callable $next) {
                    $this->next = $next;
                }

                public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                    return call_user_func($this->next, $request);
                }
            });
        });

        // Register auth:api middleware
        $middleware->addNamed('auth:api', function (Request $request, callable $next) use ($logger) {
            // Create auth middleware with 'api' guard
            $authMiddleware = new AuthMiddleware('api', $logger);

            // Process the request
            return $authMiddleware->process($request, new class ($next) implements \Psr\Http\Server\RequestHandlerInterface {
                private $next;

                public function __construct(callable $next) {
                    $this->next = $next;
                }

                public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                    return call_user_func($this->next, $request);
                }
            });
        });

        // Register auth:jwt middleware
        $middleware->addNamed('auth:jwt', function (Request $request, callable $next) use ($logger) {
            // Create auth middleware with 'jwt' guard
            $authMiddleware = new AuthMiddleware('jwt', $logger);

            // Process the request
            return $authMiddleware->process($request, new class ($next) implements \Psr\Http\Server\RequestHandlerInterface {
                private $next;

                public function __construct(callable $next) {
                    $this->next = $next;
                }

                public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                    return call_user_func($this->next, $request);
                }
            });
        });

        // Register role middleware
        $middleware->addNamed('role', function (Request $request, callable $next) use ($logger) {
            $role = $request->middlewareParams['role'] ?? 'user';

            // Create role middleware
            $roleMiddleware = new RoleMiddleware($role, $logger);

            // Process the request
            return $roleMiddleware->process($request, new class ($next) implements \Psr\Http\Server\RequestHandlerInterface {
                private $next;

                public function __construct(callable $next) {
                    $this->next = $next;
                }

                public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                    return call_user_func($this->next, $request);
                }
            });
        });

        // Register throttle middleware
        $middleware->addNamed('throttle', function (Request $request, callable $next) {
            $rate = $request->middlewareParams['throttle'] ?? '60,1';
            list($maxAttempts, $minutes) = explode(',', $rate);

            // Create throttle middleware
            $throttleMiddleware = new ThrottleMiddleware((int)$maxAttempts, (int)$minutes);

            // Process the request
            return $throttleMiddleware->process($request, new class ($next) implements \Psr\Http\Server\RequestHandlerInterface {
                private $next;

                public function __construct(callable $next) {
                    $this->next = $next;
                }

                public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                    return call_user_func($this->next, $request);
                }
            });
        });
    }

    /**
     * Register global middleware from configuration
     *
     * @param Middleware $middleware
     * @param Container $container
     * @param Config $config
     * @return void
     * @throws BindingResolutionException
     */
    protected function registerGlobalMiddleware(Middleware $middleware, Container $container, Config $config): void
    {
        // Get global middleware from configuration
        $globalMiddleware = $config->get('app.middleware.global', []);

        // Register each middleware class
        foreach ($globalMiddleware as $middlewareClass) {
            // Ensure the class exists before trying to make it
            if (class_exists($middlewareClass)) {
                $middleware->add($container->make($middlewareClass));
            }
        }
    }
}