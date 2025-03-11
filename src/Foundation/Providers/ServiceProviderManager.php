<?php

namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Providers\ApplicationServiceProvider;
use Ody\Core\Foundation\Providers\ServiceProvider;

/**
 * Service provider manager
 */
class ServiceProviderManager
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var array Registered service providers
     */
    protected $providers = [];

    /**
     * @var array Booted service providers
     */
    protected $booted = [];

    /**
     * @var array Deferred service providers mapped to their provided services
     */
    protected $deferredServices = [];

    /**
     * ServiceProviderManager constructor
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register a service provider with the application
     *
     * @param string|ServiceProvider $provider
     * @param bool $force Force register even if deferred
     * @return ServiceProvider
     */
    public function register($provider, bool $force = false): ServiceProvider
    {
        // If string is passed, resolve the provider instance
        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        // Don't register the same provider twice
        $providerClass = get_class($provider);
        if (isset($this->providers[$providerClass])) {
            return $this->providers[$providerClass];
        }

        // Check if the provider is deferred and not being forced
        if (!$force && $this->isDeferredProvider($provider)) {
            $this->registerDeferredProvider($provider);
            return $provider;
        }

        // Register the provider
        $provider->register($this->container);

        // Store the provider instance
        $this->providers[$providerClass] = $provider;

        return $provider;
    }

    /**
     * Resolve a provider instance from a class name
     *
     * @param string $provider
     * @return ServiceProvider
     */
    protected function resolveProvider(string $provider): ServiceProvider
    {
        return new $provider();
    }

    /**
     * Check if a provider is deferred
     *
     * @param ServiceProvider $provider
     * @return bool
     */
    protected function isDeferredProvider(ServiceProvider $provider): bool
    {
        return method_exists($provider, 'provides') && $provider->provides();
    }

    /**
     * Register a deferred provider
     *
     * @param ServiceProvider $provider
     * @return void
     */
    protected function registerDeferredProvider(ServiceProvider $provider): void
    {
        // Record the provider for each service it provides
        foreach ($provider->provides() as $service) {
            $this->deferredServices[$service] = get_class($provider);
        }
    }

    /**
     * Boot all registered service providers
     *
     * @return void
     */
    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            $this->bootProvider($provider);
        }
    }

    /**
     * Boot a specific provider
     *
     * @param ServiceProvider $provider
     * @return void
     */
    protected function bootProvider(ServiceProvider $provider): void
    {
        $providerClass = get_class($provider);

        if (isset($this->booted[$providerClass])) {
            return;
        }

        $provider->boot($this->container);

        $this->booted[$providerClass] = true;
    }

    /**
     * Load and boot a deferred provider by service
     *
     * @param string $service
     * @return void
     */
    public function loadDeferredProvider(string $service): void
    {
        if (!isset($this->deferredServices[$service])) {
            return;
        }

        $provider = $this->deferredServices[$service];

        // Load the provider
        if (!isset($this->providers[$provider])) {
            $this->register($provider, true);
        }

        // Boot the provider
        $this->bootProvider($this->providers[$provider]);
    }

    /**
     * Register multiple providers at once
     *
     * @param array $providers
     * @return void
     */
    public function registerProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Get registered providers
     *
     * @return array
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}