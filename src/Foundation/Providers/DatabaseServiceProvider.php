<?php
namespace Ody\Foundation\Providers;

use Ody\Container\Container;
use Ody\Foundation\Support\Config;
use PDO;

/**
 * Service provider for database services
 */
class DatabaseServiceProvider extends AbstractServiceProviderInterface
{
    /**
     * Services that should be registered as singletons
     *
     * @var array
     */
    protected $singletons = [
        PDO::class => null
    ];

    /**
     * Services that should be registered as aliases
     *
     * @var array
     */
    protected $aliases = [
        'db' => PDO::class
    ];

    /**
     * Tags for organizing services
     *
     * @var array
     */
    protected $tags = [
        'database' => [
            PDO::class,
            'db'
        ]
    ];

    /**
     * Register custom services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Register PDO connection
        $this->registerSingleton(PDO::class, function ($container) {
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
     */
    public function boot(Container $container): void
    {
        // Setup database logging if in debug mode
        if (env('APP_DEBUG', false) && $this->has('logger')) {
            $logger = $this->make('logger');
            try {
                $db = $this->make(PDO::class);
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