<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Providers;

use Ody\Container\Container;
use InfluxDB\Client;
use InfluxDB\Database;
use Ody\Foundation\Logging\InfluxDBLogger;
use Ody\Foundation\Logging\LogManager;
use Ody\Foundation\Support\Config;
use Psr\Log\LoggerInterface;

/**
 * InfluxDB Service Provider
 *
 * Registers InfluxDB services in the container.
 */
class InfluxDBServiceProvider extends ServiceProvider
{
    /**
     * Register the InfluxDB services
     *
     * @return void
     */
    public function register(): void
    {
        // Register InfluxDB client as a singleton
        $this->singleton(Client::class, function ($container) {
            $config = $container->make(Config::class);

            $host = $config->get('influxdb.host', 'localhost');
            $port = $config->get('influxdb.port', 8086);
            $username = $config->get('influxdb.username', '');
            $password = $config->get('influxdb.password', '');

            return new Client($host, $port, $username, $password);
        });

        // Register InfluxDB database as a singleton
        $this->singleton(Database::class, function ($container) {
            $config = $container->make(Config::class);
            $client = $container->make(Client::class);

            $database = $config->get('influxdb.database', 'logs');
            return $client->selectDB($database);
        });

        // Register InfluxDB logger
        $this->singleton(InfluxDBLogger::class, function ($container) {
            $config = $container->make(Config::class);

            $host = $config->get('influxdb.host', 'localhost');
            $port = $config->get('influxdb.port', 8086);
            $username = $config->get('influxdb.username', '');
            $password = $config->get('influxdb.password', '');
            $database = $config->get('influxdb.database', 'logs');
            $level = $config->get('influxdb.log_level', 'debug');

            // Get default tags
            $defaultTags = $config->get('influxdb.tags', []);

            // Determine if we should use coroutines
            $useCoroutines = $config->get('influxdb.use_coroutines', true);

            // Batch size for log entries
            $batchSize = $config->get('influxdb.batch_size', 10);

            return new InfluxDBLogger(
                $host,
                $database,
                $username,
                $password,
                $port,
                $level,
                null, // Use default formatter
                $defaultTags,
                $batchSize,
                $useCoroutines
            );
        });

        // Register an influxdb-logger alias for direct access
        $this->alias(InfluxDBLogger::class, 'influxdb-logger');
    }

    /**
     * Bootstrap the InfluxDB services
     *
     * @return void
     */
    public function boot(): void
    {
        // Get the config instance
        $config = $this->make(Config::class);

        // Ensure database exists
        if ($config->get('influxdb.ensure_db_exists', true)) {
            try {
                $client = $this->make(Client::class);
                $dbName = $config->get('influxdb.database', 'logs');

                if (!$client->existsDatabase($dbName)) {
                    $client->createDatabase($dbName);

                    // Create retention policy if configured
                    $retention = $config->get('influxdb.retention_policy');
                    if ($retention) {
                        $duration = $retention['duration'] ?? '30d';
                        $replication = $retention['replication'] ?? '1';
                        $default = $retention['default'] ?? true;

                        $client->createRetentionPolicy(
                            'default_policy',
                            $duration,
                            $replication,
                            $dbName,
                            $default
                        );
                    }
                }
            } catch (\Exception $e) {
                // Log the error but don't prevent app from starting
                error_log("Failed to create InfluxDB database: " . $e->getMessage());
            }
        }

        // Add InfluxDB driver to the LogManager if available
        if ($this->container->has(LogManager::class)) {
            $logManager = $this->container->make(LogManager::class);

            // Check if LogManager has the extend method
            if (method_exists($logManager, 'extend')) {
                $container = $this->container;

                // Register the InfluxDB driver
                $logManager->extend('influxdb', function ($config) use ($container) {
                    // Get default InfluxDB config and merge with channel-specific config
                    $influxConfig = $container->make(Config::class)->get('influxdb', []);
                    $mergedConfig = array_merge($influxConfig, $config);

                    // Create and return the InfluxDB logger
                    return new InfluxDBLogger(
                        $mergedConfig['host'] ?? 'localhost',
                        $mergedConfig['database'] ?? 'logs',
                        $mergedConfig['username'] ?? '',
                        $mergedConfig['password'] ?? '',
                        $mergedConfig['port'] ?? 8086,
                        $mergedConfig['log_level'] ?? 'debug',
                        null,
                        $mergedConfig['tags'] ?? [],
                        $mergedConfig['batch_size'] ?? 10,
                        $mergedConfig['use_coroutines'] ?? true
                    );
                });

                // Add a default InfluxDB channel configuration if not already present
                if (method_exists($logManager, 'addChannel')) {
                    if (!$logManager->hasChannel('influxdb')) {
                        $logManager->addChannel('influxdb', [
                            'driver' => 'influxdb',
                            // Default channel-specific configuration
                            'measurement' => config('influxdb.measurement', 'logs'),
                            'tags' => [
                                'channel' => 'influxdb'
                            ]
                        ]);
                    }
                }
            }
        }
    }
}