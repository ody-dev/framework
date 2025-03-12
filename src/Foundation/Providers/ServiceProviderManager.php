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
use Ody\Core\Foundation\Support\Config;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * ServiceProviderManager
 *
 * Manages the registration, booting, and discovery of service providers.
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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Config|null
     */
    protected $config;

    /**
     * ServiceProviderManager constructor
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

        // Initialize tags container
        $this->container['tag'] = [];

        // Get logger if available
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Register providers defined in configuration
     *
     * @param string $configKey The configuration key for providers list (default: app.providers)
     * @return void
     */
    public function registerConfigProviders(string $configKey = 'app.providers'): void
    {
        if (!$this->config) {
            $this->logger->warning('Cannot register config providers - no Config instance available');
            return;
        }

        // Get providers from config
        $providers = $this->config->get($configKey, []);

        if (empty($providers)) {
            $this->logger->debug("No providers found in config key '{$configKey}'");
            return;
        }

        $this->logger->debug(sprintf('Found %d providers in config', count($providers)));

        // Register each provider
        foreach ($providers as $provider) {
            try {
                $this->register($provider);
            } catch (\Throwable $e) {
                // Error is already logged in the register method
            }
        }
    }

    /**
     * Register a service provider with the application
     *
     * @param string|ServiceProvider $provider
     * @param bool $force Force register even if deferred
     * @return ServiceProvider|null The registered provider, or null on failure
     */
    public function register($provider, bool $force = false): ?ServiceProvider
    {
        try {
            // If string is passed, resolve the provider instance
            if (is_string($provider)) {
                $providerClass = $provider;
                $provider = $this->resolveProvider($provider);
            } else {
                $providerClass = get_class($provider);
            }

            // Don't register the same provider twice
            if (isset($this->providers[$providerClass])) {
                $this->logger->debug("Provider already registered: {$providerClass}");
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

            // Log success if debug mode
            $this->logger->debug("Registered service provider: {$providerClass}");

            return $provider;
        } catch (\Throwable $e) {
            // Log error
            $this->logger->error("Failed to register service provider: " . ($providerClass ?? (is_object($provider) ? get_class($provider) : 'unknown')), [
                'error' => $e->getMessage(),
                'exception' => $e
            ]);

            // Re-throw if debug mode is enabled
            if (env('APP_DEBUG', false)) {
                throw $e;
            }

            return null;
        }
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
            $providerClass = get_class($provider);

            foreach ($provider->provides() as $service) {
                $this->deferredServices[$service] = $providerClass;
            }

            // Log deferred provider if in debug mode
            $this->logger->debug("Registered deferred provider: {$providerClass}", [
                'services' => $provider->provides()
            ]);
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

            // Log success if in debug mode
            $this->logger->debug("Booted service provider: {$providerClass}");
        } catch (\Throwable $e) {
            // Log error
            $this->logger->error("Failed to boot service provider: {$providerClass}", [
                'error' => $e->getMessage(),
                'exception' => $e
            ]);

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
     * @return bool True if the provider was loaded
     */
    public function loadDeferredProvider(string $service): bool
    {
        if (!isset($this->deferredServices[$service])) {
            return false;
        }

        $providerClass = $this->deferredServices[$service];

        // Load the provider
        if (!isset($this->providers[$providerClass])) {
            $provider = $this->register($providerClass, true);

            if (!$provider) {
                return false;
            }
        }

        // Boot the provider
        $this->bootProvider($this->providers[$providerClass]);

        return true;
    }

    /**
     * Register multiple providers at once
     *
     * @param array $providers
     * @return array Successfully registered providers
     */
    public function registerProviders(array $providers): array
    {
        $registered = [];

        foreach ($providers as $provider) {
            $result = $this->register($provider);
            if ($result) {
                $registered[] = $result;
            }
        }

        return $registered;
    }

    /**
     * Boot multiple providers at once
     *
     * @param array $providers Provider class names or instances
     * @return void
     */
    public function bootProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            $providerClass = is_string($provider) ? $provider : get_class($provider);

            if (isset($this->providers[$providerClass])) {
                $this->bootProvider($this->providers[$providerClass]);
            } else {
                $this->logger->warning("Cannot boot unregistered provider: {$providerClass}");
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

    /**
     * Set the configuration repository
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
     * Set the logger
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