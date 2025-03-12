<?php
namespace Ody\Foundation\Providers;

use Ody\Container\Container;
use Ody\Foundation\Support\Config;
use Ody\Foundation\Support\Env;

/**
 * Service provider for configuration
 */
class ConfigServiceProvider extends AbstractServiceProviderInterface
{
    /**
     * Services that should be registered as singletons
     *
     * @var array
     */
    protected $singletons = [
        'config' => Config::class,
        Config::class => null
    ];

    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        $config = new Config();

        // Load config files from default location
        $configPath = env('CONFIG_PATH', base_path('config'));
        if (is_dir($configPath)) {
            $config->loadFromDirectory($configPath);
        }

        $this->instance('config', $config);
        $this->instance(Config::class, $config);
    }

    /**
     * Bootstrap any application services
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void {}
}