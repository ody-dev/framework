<?php

namespace Ody\Core\Foundation\Loaders;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Providers\ServiceProviderManager;
use Ody\Core\Foundation\Support\Config;

/**
 * Service Provider Loader
 *
 * Loads service providers from configuration
 */
class ServiceProviderLoader
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var ServiceProviderManager
     */
    protected $providerManager;

    /**
     * @var Config
     */
    protected $config;

    /**
     * ServiceProviderLoader constructor
     *
     * @param Container $container
     * @param ServiceProviderManager $providerManager
     * @param Config $config
     */
    public function __construct(
        Container $container,
        ServiceProviderManager $providerManager,
        Config $config
    ) {
        $this->container = $container;
        $this->providerManager = $providerManager;
        $this->config = $config;
    }

    /**
     * Register providers from configuration
     *
     * @return void
     */
    public function register(): void
    {
        $providers = $this->config->get('app.providers', []);

        if (empty($providers)) {
            // Log or handle the case where no providers are defined
            return;
        }

        // Register each provider
        $this->providerManager->registerProviders($providers);
    }

    /**
     * Boot all registered providers
     *
     * @return void
     */
    public function boot(): void
    {
        $this->providerManager->boot();
    }
}