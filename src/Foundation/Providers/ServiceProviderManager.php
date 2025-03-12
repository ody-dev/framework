<?php

namespace Ody\Foundation\Providers;

use Ody\Container\Container;
use Ody\Foundation\Support\Config;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service Provider Manager
 *
 * Manages the registration, bootstrapping, and lifecycle of service providers.
 * This class handles the complex internal operations, keeping the ServiceProvider class clean.
 */
class ServiceProviderManager
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * The registered service providers.
     *
     * @var array
     */
    protected array $providers = [];

    /**
     * The names of the loaded service providers.
     *
     * @var array
     */
    protected array $loaded = [];

    /**
     * The deferred services and their providers.
     *
     * @var array
     */
    protected array $deferredServices = [];

    /**
     * All of the registered service providers.
     *
     * @var array
     */
    protected array $serviceProviders = [];

    /**
     * Logger instance.
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Application configuration.
     *
     * @var Config|null
     */
    protected ?Config $config;

    /**
     * Create a new service provider manager instance.
     *
     * @param Container $container
     * @param Config|null $config
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Container $container,
        ?Config $config = null,
        ?LoggerInterface $logger = null
    ) {
        $this->container = $container;
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Register a service provider with the application.
     *
     * @param string|ServiceProvider $provider
     * @param bool $force
     * @return ServiceProvider
     */
    public function register($provider, bool $force = false): ServiceProvider
    {
        // Get the registered provider instance
        $registered = $this->getProvider($provider);

        if ($registered && ! $force) {
            return $registered;
        }

        // If the provider is not already resolved, we will resolve it now. Providers
        // are resolved when they are registered, and may register additional providers
        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        // Set the container on the provider if it doesn't have one
        if (property_exists($provider, 'container') && (!isset($provider->container) || $provider->container === null)) {
            $provider->container = $this->container;
        }

        $providerName = get_class($provider);

        if (isset($this->serviceProviders[$providerName]) && ! $force) {
            return $this->serviceProviders[$providerName];
        }

        // If the provider is deferred, and we are not forcing the registration, we'll
        // register the provider's services with the container and defer booting
        if ($this->deferredProviderCheck($provider) && ! $force) {
            $this->registerDeferredProvider($provider);
            return $provider;
        }

        // Once the provider has been registered, we can register all of its services
        // with the container. This method calls the "register" method on the provider
        $provider->register();

        // Store the provider instance
        $this->serviceProviders[$providerName] = $provider;
        $this->loaded[] = $providerName;

        return $provider;
    }

    /**
     * Get the registered service provider instance if it exists.
     *
     * @param ServiceProvider|string $provider
     * @return ServiceProvider|null
     */
    public function getProvider($provider): ?ServiceProvider
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return $this->serviceProviders[$name] ?? null;
    }

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param string $provider
     * @return ServiceProvider
     */
    protected function resolveProvider(string $provider): ServiceProvider
    {
        // Check if the container can make the provider
        if ($this->container->has($provider)) {
            return $this->container->make($provider);
        }

        // Create a new instance, passing the container if the constructor accepts it
        try {
            $reflection = new \ReflectionClass($provider);
            $constructor = $reflection->getConstructor();

            // If no constructor or the constructor doesn't require parameters, instantiate directly
            if (!$constructor || $constructor->getNumberOfRequiredParameters() === 0) {
                $instance = new $provider();
            } else {
                // Otherwise pass the container
                $instance = new $provider($this->container);
            }

            return $instance;
        } catch (\ReflectionException $e) {
            // Fallback to direct instantiation
            return new $provider();
        }
    }

    /**
     * Check if the provider is deferred.
     *
     * @param ServiceProvider $provider
     * @return bool
     */
    protected function deferredProviderCheck(ServiceProvider $provider): bool
    {
        return $provider->isDeferred() && $provider->provides();
    }

    /**
     * Register a deferred provider and its services.
     *
     * @param ServiceProvider $provider
     * @return void
     */
    protected function registerDeferredProvider(ServiceProvider $provider): void
    {
        $this->serviceProviders[get_class($provider)] = $provider;

        // Register the services that the provider provides
        foreach ($provider->provides() as $service) {
            $this->deferredServices[$service] = $provider;
        }
    }

    /**
     * Boot all of the registered providers.
     *
     * @return void
     */
    public function boot(): void
    {
        // Once all providers have been registered, we can now boot them all
        foreach ($this->serviceProviders as $provider) {
            $this->bootProvider($provider);
        }
    }

    /**
     * Boot the given service provider.
     *
     * @param ServiceProvider $provider
     * @return void
     */
    public function bootProvider(ServiceProvider $provider): void
    {
        $provider->boot();
    }

    /**
     * Register all of the configured providers from configuration.
     *
     * @param string $configKey The configuration key for providers (default: app.providers)
     * @return void
     */
    public function registerConfigProviders(string $configKey = 'app.providers'): void
    {
        if (!$this->config) {
            return;
        }

        $providers = $this->config->get($configKey, []);

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Load and boot the deferred providers if the given service is not already loaded.
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

        // If the service provider has not already been loaded, we will register it and
        // remove the service from our deferred services cache
        if (!isset($this->loaded[get_class($provider)])) {
            $this->registerAndBootDeferredProvider($provider, $service);
        }
    }

    /**
     * Register and boot a deferred provider.
     *
     * @param string|ServiceProvider $provider
     * @param string|null $service
     * @return void
     */
    protected function registerAndBootDeferredProvider($provider, ?string $service = null): void
    {
        // First, we'll register the service provider, which gives it a chance to
        // register bindings in the container and modify it in any way required
        $this->register($provider);

        if ($service) {
            unset($this->deferredServices[$service]);
        }

        // If there are bindings, we boot the provider
        $this->bootProvider($this->serviceProviders[get_class($provider)]);
    }

    /**
     * Get registered service providers.
     *
     * @return array
     */
    public function getProviders(): array
    {
        return $this->serviceProviders;
    }

    /**
     * Get loaded service provider names.
     *
     * @return array
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }

    /**
     * Get deferred services.
     *
     * @return array
     */
    public function getDeferredServices(): array
    {
        return $this->deferredServices;
    }

    /**
     * Set the container instance.
     *
     * @param Container $container
     * @return self
     */
    public function setContainer(Container $container): self
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Set the configuration repository.
     *
     * @param Config $config
     * @return self
     */
    public function setConfig(Config $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Set the logger instance.
     *
     * @param LoggerInterface $logger
     * @return self
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }
}