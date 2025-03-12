<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Middleware\MiddlewareRegistry;
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
        // Register MiddlewareRegistry
        $this->container->singleton(MiddlewareRegistry::class, function ($container) {
            return new MiddlewareRegistry($container, $container->make(LoggerInterface::class));
        });

        // Register PSR-15 middleware implementations
        $this->registerMiddlewareClasses();
    }

    /**
     * Register middleware classes
     *
     * @return void
     */
    protected function registerMiddlewareClasses(): void
    {
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

        // Register other middleware classes
        $this->container->singleton(AuthMiddleware::class, function ($container) {
            $logger = $container->make(LoggerInterface::class);
            return new AuthMiddleware('web', $logger);
        });

        $this->container->singleton(RoleMiddleware::class, function ($container) {
            $logger = $container->make(LoggerInterface::class);
            return new RoleMiddleware('user', $logger);
        });

        $this->container->singleton(ThrottleMiddleware::class, function () {
            return new ThrottleMiddleware(60, 1);
        });
    }

    /**
     * Bootstrap middleware
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        $registry = $container->make(MiddlewareRegistry::class);
        $config = $container->make(Config::class);

        // Register named middleware
        $this->registerNamedMiddleware($registry, $container);

        // Register global middleware from configuration
        $this->registerGlobalMiddleware($registry, $container, $config);

        // Register middleware groups
        $this->registerMiddlewareGroups($registry, $config);
    }

    /**
     * Register named middleware for use in routes
     *
     * @param MiddlewareRegistry $registry
     * @param Container $container
     * @return void
     */
    protected function registerNamedMiddleware(MiddlewareRegistry $registry, Container $container): void
    {
        // Get middleware from container (they were registered as singletons)
        $registry->add('auth', $container->make(AuthMiddleware::class));
        $registry->add('auth:api', new AuthMiddleware('api', $container->make(LoggerInterface::class)));
        $registry->add('auth:jwt', new AuthMiddleware('jwt', $container->make(LoggerInterface::class)));

        $registry->add('role', $container->make(RoleMiddleware::class));
        $registry->add('role:admin', new RoleMiddleware('admin', $container->make(LoggerInterface::class)));
        $registry->add('role:user', new RoleMiddleware('user', $container->make(LoggerInterface::class)));

        $registry->add('throttle', $container->make(ThrottleMiddleware::class));
        $registry->add('throttle:60,1', new ThrottleMiddleware(60, 1));
        $registry->add('throttle:1000,60', new ThrottleMiddleware(1000, 60));

        $registry->add('cors', $container->make(CorsMiddleware::class));
        $registry->add('json', $container->make(JsonBodyParserMiddleware::class));
        $registry->add('log', $container->make(LoggingMiddleware::class));

        // Register middleware defined in config
        $namedMiddleware = config('app.middleware.named', []);

        foreach ($namedMiddleware as $name => $class) {
            // Skip if already registered
            if ($registry->has($name)) {
                continue;
            }

            // If class exists and container has it, register the middleware
            if (class_exists($class) && $container->has($class)) {
                $registry->add($name, $container->make($class));
            }
            // Otherwise register the class name for later resolution
            else {
                $registry->add($name, $class);
            }
        }
    }

    /**
     * Register global middleware from configuration
     *
     * @param MiddlewareRegistry $registry
     * @param Container $container
     * @param Config $config
     * @return void
     */
    protected function registerGlobalMiddleware(MiddlewareRegistry $registry, Container $container, Config $config): void
    {
        // Get global middleware from configuration
        $globalMiddleware = $config->get('app.middleware.global', []);

        // Register each middleware class
        foreach ($globalMiddleware as $middlewareClass) {
            // If container has the middleware, add it directly
            if ($container->has($middlewareClass)) {
                $registry->addGlobal($container->make($middlewareClass));
            }
            // Otherwise add the class name for later resolution
            else {
                $registry->addGlobal($middlewareClass);
            }
        }
    }

    /**
     * Register middleware groups from configuration
     *
     * @param MiddlewareRegistry $registry
     * @param Config $config
     * @return void
     */
    protected function registerMiddlewareGroups(MiddlewareRegistry $registry, Config $config): void
    {
        // Get middleware groups from configuration
        $middlewareGroups = $config->get('app.middleware.groups', []);

        // Register each group
        foreach ($middlewareGroups as $name => $middleware) {
            $registry->addGroup($name, $middleware);
        }
    }
}