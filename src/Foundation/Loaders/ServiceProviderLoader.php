<?php

namespace Ody\Core\Foundation\Loaders;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Providers\ServiceProviderManager;
use Ody\Core\Foundation\Support\Config;
use Ody\Core\Foundation\Logger;

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
     * @var Logger|null
     */
    protected $logger;

    /**
     * ServiceProviderLoader constructor
     *
     * @param Container $container
     * @param ServiceProviderManager $providerManager
     * @param Config $config
     * @param Logger|null $logger
     */
    public function __construct(
        Container $container,
        ServiceProviderManager $providerManager,
        Config $config,
        ?Logger $logger = null
    ) {
        $this->container = $container;
        $this->providerManager = $providerManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Register providers from configuration
     *
     * @return void
     */
    public function register(): void
    {
        // Get providers from config
        $providers = $this->config->get('app.providers', []);

        // Register each provider
        foreach ($providers as $provider) {
            try {
                $this->providerManager->register($provider);
            } catch (\Throwable $e) {
                if ($this->logger) {
                    $this->logger->error('Failed to register provider: ' . $provider, [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                }

                // Re-throw in debug mode
                if (env('APP_DEBUG', false)) {
                    throw $e;
                }
            }
        }
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