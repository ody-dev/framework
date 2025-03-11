<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Logger;
use Ody\Core\Foundation\Middleware\Middleware;
use Ody\Core\Foundation\Middleware\CorsMiddleware;
use Ody\Core\Foundation\Middleware\JsonBodyParserMiddleware;
use Ody\Core\Foundation\Middleware\LoggingMiddleware;
use Ody\Core\Foundation\Middleware\AuthMiddleware;
use Ody\Core\Foundation\Middleware\RoleMiddleware;
use Ody\Core\Foundation\Middleware\ThrottleMiddleware;

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
            $config = $container->make('config');
            $corsConfig = config('app.cors') ?? [];

            return new CorsMiddleware($corsConfig);
        });

        $this->container->singleton(JsonBodyParserMiddleware::class, function () {
            return new JsonBodyParserMiddleware();
        });

        $this->container->singleton(LoggingMiddleware::class, function ($container) {
            $logger = $container->make(Logger::class);
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
        $logger = $container->make(Logger::class);

        // Register named middleware
        $this->registerNamedMiddleware($middleware, $logger);

        // Register global middleware
        $this->registerGlobalMiddleware($middleware, $container);
    }

    /**
     * Register named middleware for use in routes
     *
     * @param Middleware $middleware
     * @param Logger $logger
     * @return void
     */
    protected function registerNamedMiddleware(Middleware $middleware, Logger $logger): void
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
     * Register global middleware
     *
     * @param Middleware $middleware
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    protected function registerGlobalMiddleware(Middleware $middleware, Container $container): void
    {
        // Add PSR-15 CORS middleware
        $middleware->add($container->make(CorsMiddleware::class));

        // Add PSR-15 JSON body parser middleware
        $middleware->add($container->make(JsonBodyParserMiddleware::class));

        // Add PSR-15 logging middleware
        $middleware->add($container->make(LoggingMiddleware::class));
    }
}