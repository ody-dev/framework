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
use Ody\Core\Foundation\Facades\App;
use Ody\Core\Foundation\Logger;
use Ody\Core\Foundation\Support\AliasLoader;
use Ody\Core\Foundation\Support\Config;
use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Router;
use Ody\Core\Foundation\Facades\Facade;

/**
 * Service provider for facades
 */
class FacadeServiceProvider extends AbstractServiceProvider
{
    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Set the container on the Facade class
        Facade::setFacadeContainer($this->container);

        // Register core services for facades
        $this->container->alias(Router::class, 'router');
        $this->container->alias(Config::class, 'config');

        // Register request singleton
        if (!$this->container->bound('request')) {
            $this->container->singleton('request', function () {
                return Request::createFromGlobals();
            });
        }

        // Register response singleton
        if (!$this->container->bound('response')) {
            $this->container->singleton('response', function () {
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
        $config = $container->make(Config::class);
        $aliases = $config->get('app.aliases', []);

        // Create alias loader with the configured aliases
        $loader = AliasLoader::getInstance($aliases);

        // Register the alias autoloader
        $loader->register();
    }
}