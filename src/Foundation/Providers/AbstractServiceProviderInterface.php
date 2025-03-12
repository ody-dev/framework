<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Providers;

use Ody\Container\Contracts\BindingResolutionException;
use Ody\Container\Container;
use Ody\Foundation\Logging\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * base service provider class with streamlined registration capabilities
 */
abstract class AbstractServiceProviderInterface implements ServiceProviderInterface
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

        // Get logger if available
        if ($container->has(LoggerInterface::class)) {
            $this->logger = $container->make(LoggerInterface::class);
        } else {
            $this->logger = new NullLogger();
        }

        array_walk($this->bindings, function ($concrete, $abstract) {
            // Register bindings
            $this->registerBinding($abstract, $concrete);
            // Register singletons
            $this->registerSingleton($abstract, $concrete);
        });

        array_walk($this->aliases, function ($abstract, $alias) use ($container) {
            // Register aliases
            $container->alias($abstract, $alias);
        });

        array_walk($this->tags, function ($abstracts, $tag) use ($container) {
            // Register tags
            array_walk($abstracts, fn ($abstract) => $this->tag($abstract, $tag));
        });

        // Call the provider's custom registration logic
        $this->registerServices();
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

        if (!isset($this->container['tag'])) {
            $this->container->instance('tag', []);
        }

        // Get current tags
        $allTags = $this->container['tag'];

        // Register each tag
        foreach ($tags as $tag) {
            // Add the service to the tag
            $allTags[$tag][] = $abstract;
        }

        // Update container with all modified tags
        $this->container->instance('tag', $allTags);
    }

    /**
     * Get all services registered with a given tag
     *
     * @param string $tag
     * @return array
     */
    protected function tagged(string $tag): array
    {
        if (!isset($this->container['tag']) || !isset($this->container['tag'][$tag])) {
            return [];
        }

        return $this->container['tag'][$tag];
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
     * @throws BindingResolutionException
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