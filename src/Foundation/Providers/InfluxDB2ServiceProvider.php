<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Providers;

use InfluxDB2\Client;
use Ody\Foundation\Logging\InfluxDB2Logger;
use Ody\Foundation\Logging\LogManager;
use Ody\Foundation\Support\Config;
use Psr\Log\LoggerInterface;

/**
 * InfluxDB 2.x Service Provider
 *
 * Registers InfluxDB 2.x services in the container.
 */
class InfluxDB2ServiceProvider extends ServiceProvider
{
    /**
     * Register the InfluxDB 2.x services
     *
     * @return void
     */
    public function register(): void
    {
        // Register InfluxDB 2.x client as a singleton with explicit factory function
        $this->container->singleton(Client::class, function ($container) {
            $config = $container->make(Config::class);

            // Create client options array
            $options = [
                "url" => $config->get('influxdb.url', 'http://localhost:8086'),
                "token" => $config->get('influxdb.token', ''),
                "bucket" => $config->get('influxdb.bucket', 'logs'),
                "org" => $config->get('influxdb.org', 'organization'),
                "precision" => \InfluxDB2\Model\WritePrecision::S
            ];

            return new Client($options);
        });

        // Register InfluxDB2Logger
        $this->container->singleton(InfluxDB2Logger::class, function ($container) {
            $config = $container->make(Config::class);
            $client = $container->make(Client::class);

            return new InfluxDB2Logger(
                $config->get('influxdb.url', 'http://localhost:8086'),
                $config->get('influxdb.token', ''),
                $config->get('influxdb.org', 'organization'),
                $config->get('influxdb.bucket', 'logs'),
                $config->get('influxdb.log_level', 'debug'),
                null, // Use default formatter
                $config->get('influxdb.tags', []),
                $config->get('influxdb.use_coroutines', true)
            );
        });

        // Register an influxdb-logger alias for direct access
        $this->container->alias(InfluxDB2Logger::class, 'influxdb2-logger');
    }

    /**
     * Bootstrap the InfluxDB 2.x services
     *
     * @return void
     */
    public function boot(): void
    {
    }
}