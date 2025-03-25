<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace App\Providers;

use App\Http\Middleware\DatabaseMiddleware;
use Doctrine\DBAL\Types\Type;
use Ody\DB\Doctrine\Types\JsonType;
use Ody\Foundation\Providers\ServiceProvider;

class DoctrineIntegrationProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the database middleware
        $this->container->singleton(DatabaseMiddleware::class, function ($app) {
            return new DatabaseMiddleware();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom Doctrine types
        $this->registerDoctrineTypes();

        // Add middleware to global middleware stack
        $this->registerMiddleware();
    }

    /**
     * Register custom Doctrine types
     */
    protected function registerDoctrineTypes(): void
    {
        try {
            // Check if the JsonType is already registered
            if (!Type::hasType('json')) {
                Type::addType('json', JsonType::class);
            }

            // Register more custom types here as needed
        } catch (\Throwable $e) {
            logger()->error('Failed to register Doctrine types: ' . $e->getMessage());
        }
    }

    /**
     * Register the database middleware
     */
    protected function registerMiddleware(): void
    {
        // Add the middleware to the global middleware stack if your framework supports it
        if (method_exists($this->container, 'middleware') && method_exists($this->container->middleware(), 'push')) {
            $this->container->middleware()->push(DatabaseMiddleware::class);
        }
    }
}