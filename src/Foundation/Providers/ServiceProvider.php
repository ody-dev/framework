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

/**
 * Interface for service providers
 */
interface ServiceProvider
{
    /**
     * Register services in the container
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void;

    /**
     * Bootstrap any application services
     * This is called after all services are registered
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void;
}