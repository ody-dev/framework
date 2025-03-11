<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use PDO;

/**
 * Service provider for database services
 */
class DatabaseServiceProvider extends AbstractServiceProvider
{
    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Register PDO connection
        $this->container->singleton(PDO::class, function ($container) {
            $config = $container->make('config');

            if (!isset($config['database'])) {
                throw new \RuntimeException('Database configuration not found');
            }

            $dbConfig = $config['database'];

            // Create PDO connection
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $dbConfig['host'],
                $dbConfig['database']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            return new PDO(
                $dsn,
                $dbConfig['username'],
                $dbConfig['password'],
                $options
            );
        });

        // Register db shorthand alias
        $this->container->alias(PDO::class, 'db');
    }

    /**
     * Bootstrap database services
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Could add database logging or connection pooling here if needed
    }
}