<?php
namespace Ody\Core\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
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
            $dsn = $this->buildDsn($connection);
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
     * Build DSN string based on connection configuration
     *
     * @param array $connection
     * @return string
     */
    protected function buildDsn(array $connection): string
    {
        $driver = $connection['driver'] ?? 'mysql';

        switch ($driver) {
            case 'sqlite':
                return "sqlite:{$connection['database']}";

            case 'pgsql':
                return sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s',
                    $connection['host'] ?? 'localhost',
                    $connection['port'] ?? '5432',
                    $connection['database'] ?? 'forge',
                    $connection['username'] ?? 'forge',
                    $connection['password'] ?? ''
                );

            case 'sqlsrv':
                return sprintf(
                    'sqlsrv:Server=%s,%s;Database=%s',
                    $connection['host'] ?? 'localhost',
                    $connection['port'] ?? '1433',
                    $connection['database'] ?? 'forge'
                );

            case 'mysql':
            default:
                return sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $connection['host'] ?? 'localhost',
                    $connection['port'] ?? '3306',
                    $connection['database'] ?? 'forge',
                    $connection['charset'] ?? 'utf8mb4'
                );
        }
    }

    /**
     * Bootstrap database services
     *
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        // Could add database logging or connection pooling here if needed

        // Setup database logging if in debug mode
        if (env('APP_DEBUG', false) && $container->has('logger')) {
            $logger = $container->make('logger');
            try {
                $db = $container->make(PDO::class);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Log connection success
                $logger->info('Database connection established successfully');
            } catch (\PDOException $e) {
                // Log connection error
                $logger->error('Database connection failed', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
            }
        }
    }
}