<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Core\Foundation\Logging;

use Psr\Log\LogLevel;

/**
 * LogManager
 * Factory and manager for loggers
 */
class LogManager
{
    /**
     * @var array Default configuration
     */
    protected array $config = [
        'default' => 'file',
        'channels' => [
            'file' => [
                'driver' => 'file',
                'path' => 'logs/app.log',
                'level' => LogLevel::DEBUG,
                'formatter' => 'line',
                'rotate' => false,
                'max_file_size' => 10485760
            ],
            'stdout' => [
                'driver' => 'stream',
                'stream' => 'php://stdout',
                'level' => LogLevel::DEBUG,
                'formatter' => 'line'
            ],
            'stderr' => [
                'driver' => 'stream',
                'stream' => 'php://stderr',
                'level' => LogLevel::ERROR,
                'formatter' => 'line'
            ],
            'daily' => [
                'driver' => 'file',
                'path' => 'logs/daily-',
                'level' => LogLevel::DEBUG,
                'formatter' => 'line',
                'rotate' => true,
                'max_file_size' => 5242880
            ],
        ]
    ];

    /**
     * @var LoggerInterface[] Array of created loggers
     */
    protected array $loggers = [];

    /**
     * Constructor
     *
     * @param array $config Optional configuration to override defaults
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get a logger instance
     *
     * @param string|null $channel Channel name or null for default
     * @return LoggerInterface
     */
    public function channel(?string $channel = null): LoggerInterface
    {
        $channel = $channel ?? $this->config['default'];

        if (!isset($this->config['channels'][$channel])) {
            throw new \InvalidArgumentException("Log channel '{$channel}' is not defined");
        }

        if (!isset($this->loggers[$channel])) {
            $this->loggers[$channel] = $this->createLogger($channel);
        }

        return $this->loggers[$channel];
    }

    /**
     * Create a new logger instance based on config
     *
     * @param string $channel
     * @return LoggerInterface
     */
    protected function createLogger(string $channel): LoggerInterface
    {
        $config = $this->config['channels'][$channel];

        if (!isset($config['driver'])) {
            throw new \InvalidArgumentException("Log channel '{$channel}' has no driver specified");
        }

        // Create formatter
        $formatter = $this->createFormatter($config);

        // Create logger based on driver
        switch ($config['driver']) {
            case 'file':
                return $this->createFileLogger($config, $formatter);

            case 'stream':
                return $this->createStreamLogger($config, $formatter);

            case 'callable':
                if (!isset($config['handler']) || !is_callable($config['handler'])) {
                    throw new \InvalidArgumentException("Log channel '{$channel}' requires a callable handler");
                }

                return new CallableLogger(
                    $config['handler'],
                    $config['level'] ?? LogLevel::DEBUG,
                    $formatter
                );

            case 'null':
                return new NullLogger(
                    $config['level'] ?? LogLevel::DEBUG,
                    $formatter
                );

            case 'group':
                $loggers = [];
                foreach ($config['channels'] ?? [] as $subChannel) {
                    $loggers[] = $this->channel($subChannel);
                }

                return new GroupLogger(
                    $loggers,
                    $config['level'] ?? LogLevel::DEBUG,
                    $formatter
                );

            default:
                throw new \InvalidArgumentException("Log driver '{$config['driver']}' is not supported");
        }
    }

    /**
     * Create a formatter instance based on config
     *
     * @param array $config
     * @return FormatterInterface
     */
    protected function createFormatter(array $config): FormatterInterface
    {
        $formatterType = $config['formatter'] ?? 'line';

        switch ($formatterType) {
            case 'json':
                return new JsonFormatter();

            case 'line':
            default:
                return new LineFormatter(
                    $config['format'] ?? null,
                    $config['date_format'] ?? null
                );
        }
    }

    /**
     * Create a file logger
     *
     * @param array $config
     * @param FormatterInterface $formatter
     * @return FileLogger
     */
    protected function createFileLogger(array $config, FormatterInterface $formatter): FileLogger
    {
        // Add date suffix for daily files
        $path = $config['path'];
        if (strpos($path, 'daily-') !== false) {
            $path = str_replace('daily-', 'daily-' . date('Y-m-d') . '-', $path);
        }

        return new FileLogger(
            $path,
            $config['level'] ?? LogLevel::DEBUG,
            $formatter,
            $config['rotate'] ?? false,
            $config['max_file_size'] ?? 10485760
        );
    }

    /**
     * Create a stream logger
     *
     * @param array $config
     * @param FormatterInterface $formatter
     * @return StreamLogger
     */
    protected function createStreamLogger(array $config, FormatterInterface $formatter): StreamLogger
    {
        if (!isset($config['stream'])) {
            throw new \InvalidArgumentException("Stream logger requires a 'stream' configuration value");
        }

        return new StreamLogger(
            $config['stream'],
            $config['level'] ?? LogLevel::DEBUG,
            $formatter,
            $config['close_on_destruct'] ?? false
        );
    }

    /**
     * Create a custom logger with specific options
     *
     * @param string $driver
     * @param array $options
     * @return LoggerInterface
     */
    public function custom(string $driver, array $options = []): LoggerInterface
    {
        $formatter = $this->createFormatter($options);

        switch ($driver) {
            case 'file':
                return $this->createFileLogger($options, $formatter);

            case 'stream':
                return $this->createStreamLogger($options, $formatter);

            case 'callable':
                if (!isset($options['handler']) || !is_callable($options['handler'])) {
                    throw new \InvalidArgumentException("Callable logger requires a callable handler");
                }

                return new CallableLogger(
                    $options['handler'],
                    $options['level'] ?? LogLevel::DEBUG,
                    $formatter
                );

            case 'null':
                return new NullLogger(
                    $options['level'] ?? LogLevel::DEBUG,
                    $formatter
                );

            default:
                throw new \InvalidArgumentException("Log driver '{$driver}' is not supported");
        }
    }
}