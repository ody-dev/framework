<?php

namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Support\Config;
use Psr\Log\LoggerInterface;

/**
 * service provider manager
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
     * @var array Tagged services
     */
    protected $tags = [];

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * ServiceProviderManager constructor
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        // Initialize tags container
        $this->container['tag'] = [];

        // Get logger if available
        if ($container->has(LoggerInterface::class)) {
            $this->logger = $container->make(LoggerInterface::class);
        }
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

        try {
            // Register the provider
            $provider->register($this->container);

            // Store the provider instance
            $this->providers[$providerClass] = $provider;

            // Log success if logger is available
            if ($this->logger && env('APP_DEBUG', false)) {
                $this->logger->debug("Registered service provider: {$providerClass}");
            }
        } catch (\Throwable $e) {
            // Log error if logger is available
            if ($this->logger) {
                $this->logger->error("Failed to register service provider: {$providerClass}", [
                    'error' => $e->getMessage(),
                    'exception' => $e
                ]);
            }

            // Re-throw if debug mode is enabled
            if (env('APP_DEBUG', false)) {
                throw $e;
            }
        }

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
        if ($this->container->has($provider)) {
            return $this->container->make($provider);
        }

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
        // Check if provider implements isDeferred method (from our AbstractServiceProvider)
        if (method_exists($provider, 'isDeferred')) {
            return $provider->isDeferred();
        }

        // Otherwise check if it has provides method with non-empty result
        return method_exists($provider, 'provides') && !empty($provider->provides());
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
        if (method_exists($provider, 'provides')) {
            foreach ($provider->provides() as $service) {
                $this->deferredServices[$service] = get_class($provider);
            }

            // Log deferred provider if logger is available
            if ($this->logger && env('APP_DEBUG', false)) {
                $this->logger->debug("Registered deferred provider: " . get_class($provider), [
                    'services' => $provider->provides()
                ]);
            }
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
    public function bootProvider(ServiceProvider $provider): void
    {
        $providerClass = get_class($provider);

        if (isset($this->booted[$providerClass])) {
            return;
        }

        try {
            // Boot the provider
            $provider->boot($this->container);

            // Mark as booted
            $this->booted[$providerClass] = true;

            // Log success if logger is available
            if ($this->logger && env('APP_DEBUG', false)) {
                $this->logger->debug("Booted service provider: {$providerClass}");
            }
        } catch (\Throwable $e) {
            // Log error if logger is available
            if ($this->logger) {
                $this->logger->error("Failed to boot service provider: {$providerClass}", [
                    'error' => $e->getMessage(),
                    'exception' => $e
                ]);
            }

            // Re-throw if debug mode is enabled
            if (env('APP_DEBUG', false)) {
                throw $e;
            }
        }
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
            try {
                $this->register($provider);
            } catch (\Throwable $e) {
                // Error is already logged in the register method
            }
        }
    }

    /**
     * Boot multiple providers at once
     *
     * @param array $providers
     * @return void
     */
    public function bootProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            $provider = is_string($provider) ? $provider : get_class($provider);

            if (isset($this->providers[$provider])) {
                $this->bootProvider($this->providers[$provider]);
            }
        }
    }

    /**
     * Get services registered with a specific tag
     *
     * @param string $tag
     * @return array
     */
    public function getTagged(string $tag): array
    {
        return $this->container['tag'][$tag] ?? [];
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

    /**
     * Get booted providers
     *
     * @return array
     */
    public function getBootedProviders(): array
    {
        return $this->booted;
    }

    /**
     * Get deferred services
     *
     * @return array
     */
    public function getDeferredServices(): array
    {
        return $this->deferredServices;
    }

    /**
     * Check if provider is registered
     *
     * @param string $provider
     * @return bool
     */
    public function isRegistered(string $provider): bool
    {
        return isset($this->providers[$provider]);
    }

    /**
     * Check if provider is booted
     *
     * @param string $provider
     * @return bool
     */
    public function isBooted(string $provider): bool
    {
        return isset($this->booted[$provider]);
    }

    /**
     * Check if a service is deferred
     *
     * @param string $service
     * @return bool
     */
    public function isDeferred(string $service): bool
    {
        return isset($this->deferredServices[$service]);
    }
}