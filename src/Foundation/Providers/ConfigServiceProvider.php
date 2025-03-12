<?php
namespace Ody\Foundation\Providers;

use Ody\Container\Container;
use Ody\Foundation\Support\Config;
use Ody\Foundation\Support\Env;

/**
 * Service provider for configuration
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Services that should be registered as singletons
     *
     * @var array
     */
    protected array $singletons = [
        'config' => Config::class,
        Config::class => null
    ];

    /**
     * Register custom services
     *
     * @return void
     */
    public function register(): void
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
     * @return void
     */
    public function boot(): void {}
}