<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Support\Config;
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
            /** @var Config $config */
            $config = $container->make(Config::class);

            // Get default connection name
            $default = $config->get('database.default', 'mysql');

            // Get connection configuration
            $connection = $config->get("database.connections.{$default}");

            if (!$connection) {
                throw new \RuntimeException("Database connection [{$default}] not configured");
            }

            // Create PDO connection
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $connection['driver'] ?? 'mysql',
                $connection['host'] ?? 'localhost',
                $connection['port'] ?? '3306',
                $connection['database'] ?? 'api',
                $connection['charset'] ?? 'utf8mb4'
            );

            $options = $connection['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            return new PDO(
                $dsn,
                $connection['username'] ?? 'root',
                $connection['password'] ?? '',
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