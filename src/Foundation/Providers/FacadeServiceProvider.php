<?php
namespace Ody\Foundation\Providers;

use Ody\Container\Container;
use Ody\Foundation\Facades\App;
use Ody\Foundation\Support\AliasLoader;
use Ody\Foundation\Support\Config;
use Ody\Foundation\Http\Request;
use Ody\Foundation\Http\Response;
use Ody\Foundation\Router;
use Ody\Foundation\Facades\Facade;

/**
 * Service provider for facades
 */
class FacadeServiceProvider extends AbstractServiceProviderInterface
{
    /**
     * Services that should be registered as aliases
     *
     * @var array
     */
    protected $aliases = [
        'router' => Router::class,
        'config' => Config::class
    ];

    /**
     * Services that should be registered as singletons
     *
     * @var array
     */
    protected $singletons = [
        'request' => null,
        'response' => null
    ];

    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Set the container on the Facade class
        Facade::setFacadeContainer($this->container);

        // Register request singleton
        if (!$this->has('request')) {
            $this->registerSingleton('request', function () {
                return Request::createFromGlobals();
            });
        }

        // Register response singleton
        if (!$this->has('response')) {
            $this->registerSingleton('response', function () {
                return new Response();
            });
        }
    }

    /**
     * Bootstrap any application services
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Get aliases from config
        $config = $this->make(Config::class);
        $aliases = $config->get('app.aliases', []);

        // Create alias loader with the configured aliases
        $loader = AliasLoader::getInstance($aliases);

        // Register the alias autoloader
        $loader->register();
    }
}