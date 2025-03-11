<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;

/**
 * Base service provider class
 */
abstract class AbstractServiceProvider implements ServiceProvider
{
    /**
     * @var Container
     */
    protected $container;

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
     * Register services in the container
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $this->container = $container;

        // Register bindings
        foreach ($this->bindings as $abstract => $concrete) {
            $container->bind($abstract, $concrete);
        }

        // Register singletons
        foreach ($this->singletons as $abstract => $concrete) {
            $container->singleton($abstract, $concrete);
        }

        // Call the provider's custom registration logic
        $this->registerServices();
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
}