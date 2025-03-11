<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Support\Config;

/**
 * Service provider for configuration
 */
class ConfigServiceProvider extends AbstractServiceProvider
{
    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Config is already registered in the bootstrap process
        // This provider exists mainly to ensure Config is available
        // for other providers and to maintain the service provider pattern
    }

    /**
     * Bootstrap any application services
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        // No bootstrapping needed for config
    }
}