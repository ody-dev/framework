<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace App\Providers;

use App\Repositories\UserRepository;
use Ody\Foundation\Providers\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register the application's repositories.
     */
    public function register(): void
    {
        $this->container->singleton(UserRepository::class, function ($app) {
            return new UserRepository();
        });

        // Register other repositories here as needed
    }

    /**
     * Bootstrap any repository services.
     */
    public function boot(): void
    {
        // No initialization needed here
    }
}