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
use Psr\Log\LoggerInterface;

/**
 * base service provider class with streamlined registration capabilities
 */
abstract class AbstractServiceProvider implements ServiceProvider
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * Services that should be registered as singletons
     *
     * @var array
     */
    protected $singletons = [];

    /**
     * Services that should be registered as bindings
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * Services that should be registered as aliases
     *
     * @var array
     */
    protected $aliases = [];

    /**
     * Tags for grouping services
     *
     * @var array
     */
    protected $tags = [];

    /**
     * Whether to defer registration until service is needed
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register services in the container
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $this->container = $container;

        try {
            // Get logger if available
            if ($container->has(LoggerInterface::class)) {
                $this->logger = $container->make(LoggerInterface::class);
            }

            // Register bindings
            foreach ($this->bindings as $abstract => $concrete) {
                $this->registerBinding($abstract, $concrete);
            }

            // Register singletons
            foreach ($this->singletons as $abstract => $concrete) {
                $this->registerSingleton($abstract, $concrete);
            }

            // Register aliases
            foreach ($this->aliases as $alias => $abstract) {
                $container->alias($abstract, $alias);
            }

            // Register tags
            foreach ($this->tags as $tag => $abstracts) {
                foreach ((array) $abstracts as $abstract) {
                    $this->tag($abstract, $tag);
                }
            }

            // Call the provider's custom registration logic
            $this->registerServices();
        } catch (\Throwable $e) {
            // Log exception if logger is available
            if ($this->logger) {
                $this->logger->error('Error registering services: ' . $e->getMessage(), [
                    'provider' => static::class,
                    'exception' => $e
                ]);
            }

            // Re-throw if in debug mode
            if (env('APP_DEBUG', false)) {
                throw $e;
            }
        }
    }

    /**
     * Register a binding with the container
     *
     * @param string $abstract
     * @param mixed $concrete
     * @param bool $shared
     * @return void
     */
    protected function registerBinding(string $abstract, $concrete, bool $shared = false): void
    {
        // If the concrete value is null, use the abstract as the concrete
        if ($concrete === null) {
            $concrete = $abstract;
        }

        // Register the binding
        $this->container->bind($abstract, $concrete, $shared);

        // Log registration if logger is available
        if ($this->logger && env('APP_DEBUG', false)) {
            $this->logger->debug("Registered binding for {$abstract}");
        }
    }

    /**
     * Register a singleton with the container
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    protected function registerSingleton(string $abstract, $concrete = null): void
    {
        // If the concrete value is null, use the abstract as the concrete
        if ($concrete === null) {
            $concrete = $abstract;
        }

        // Register the singleton
        $this->container->singleton($abstract, $concrete);
    }

    /**
     * Register a tag for a service
     *
     * @param string $abstract
     * @param string|array $tags
     * @return void
     */
    protected function tag(string $abstract, $tags): void
    {
        // Convert single tag to array
        $tags = (array) $tags;

        // Register each tag
        foreach ($tags as $tag) {
            // If tag doesn't exist yet in the container, create it
            if (!isset($this->container['tag'][$tag])) {
                $this->container['tag'][$tag] = [];
            }

            // Add service to the tag
            $this->container['tag'][$tag][] = $abstract;
        }
    }

    /**
     * Get all services registered with a given tag
     *
     * @param string $tag
     * @return array
     */
    protected function tagged(string $tag): array
    {
        return $this->container['tag'][$tag] ?? [];
    }

    /**
     * Register a factory with the container
     *
     * @param string $abstract
     * @param callable $factory
     * @return void
     */
    protected function factory(string $abstract, callable $factory): void
    {
        $this->container->bind($abstract, $factory);
    }

    /**
     * Check if a service is registered in the container
     *
     * @param string $abstract
     * @return bool
     */
    protected function has(string $abstract): bool
    {
        return $this->container->has($abstract) || $this->container->bound($abstract);
    }

    /**
     * Get a service from the container
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     */
    protected function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * Register a service instance with the container
     *
     * @param string $abstract
     * @param mixed $instance
     * @return void
     */
    protected function instance(string $abstract, $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Override in child classes
    }

    /**
     * Bootstrap any application services
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Override in child classes if needed
    }

    /**
     * Determine if the provider is deferred
     *
     * @return bool
     */
    public function isDeferred(): bool
    {
        return $this->defer;
    }

    /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides(): array
    {
        return array_merge(
            array_keys($this->singletons),
            array_keys($this->bindings)
        );
    }
}