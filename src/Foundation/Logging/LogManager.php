<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Logging;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * LogManager with lazy-loading support for custom drivers
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
     * @var array Custom driver creators
     */
    protected array $customCreators = [];

    /**
     * @var bool Whether we're in debug mode
     */
    protected bool $debug = false;

    /**
     * @var bool Flag to prevent circular resolution of channels
     */
    protected array $resolvingChannels = [];

    /**
     * Constructor
     *
     * @param array $config Optional configuration to override defaults
     */
    public function __construct(array $config = [])
    {
        // Merge custom config with defaults
        $this->config = array_replace_recursive($this->config, $config);

        // Set debug mode
        $this->debug = (bool)env('APP_DEBUG', false);
    }

    /**
     * Get a logger instance
     *
     * @param string|null $channel Channel name or null for default
     * @return LoggerInterface
     */
    public function channel(?string $channel = null): LoggerInterface
    {
        // Use default channel if none specified
        $channel = $channel ?? $this->config['default'];

        // If channel doesn't exist in config, fail gracefully
        if (!isset($this->config['channels'][$channel])) {
            // Log the error if in debug mode
            if ($this->debug) {
                error_log("Log channel '{$channel}' is not defined. Using default channel.");
            }

            // Fallback to default channel
            $channel = $this->config['default'];

            // If default channel also doesn't exist, use emergency fallback
            if (!isset($this->config['channels'][$channel])) {
                if ($this->debug) {
                    error_log("Default log channel '{$channel}' is not defined. Using NullLogger.");
                }
                return new NullLogger();
            }
        }

        // Return cached instance if available
        if (isset($this->loggers[$channel])) {
            return $this->loggers[$channel];
        }

        // Detect circular dependencies
        if (isset($this->resolvingChannels[$channel])) {
            error_log("Circular dependency detected for log channel '{$channel}'. Using NullLogger to break the cycle.");
            return new NullLogger();
        }

        // Mark this channel as being resolved to detect circular dependencies
        $this->resolvingChannels[$channel] = true;

        // Create new logger instance
        try {
            $this->loggers[$channel] = $this->createLogger($channel);
        } catch (\Throwable $e) {
            // Log the error
            error_log("Failed to create logger for channel '{$channel}': " . $e->getMessage());

            // Fallback to NullLogger to avoid disrupting application
            $this->loggers[$channel] = new NullLogger();
        } finally {
            // Done resolving this channel
            unset($this->resolvingChannels[$channel]);
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

        $driver = $config['driver'];

        // Check for custom driver creator first
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver, $config);
        }

        // Handle "custom" driver with "via" class
        if ($driver === 'custom' && isset($config['via'])) {
            return $this->createCustomLogger($config);
        }

        // Create formatter
        $formatter = $this->createFormatter($config);

        // Create logger based on driver
        switch ($driver) {
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
                return $this->createGroupLogger($config, $formatter);

            default:
                // Try to use a registered custom driver creator
                if (in_array($driver, array_keys($this->customCreators))) {
                    return $this->callCustomCreator($driver, $config);
                }

                throw new \InvalidArgumentException("Log driver '{$driver}' is not supported");
        }
    }

    /**
     * Create a group logger
     *
     * @param array $config
     * @param FormatterInterface $formatter
     * @return GroupLogger
     */
    protected function createGroupLogger(array $config, FormatterInterface $formatter): GroupLogger
    {
        // Ensure channels array exists
        if (!isset($config['channels']) || !is_array($config['channels']) || empty($config['channels'])) {
            throw new \InvalidArgumentException("Group logger requires a 'channels' configuration array");
        }

        // Create individual loggers for each channel
        $loggers = [];
        $errors = [];

        foreach ($config['channels'] as $channelName) {
            // Skip if the channel would cause a circular reference
            if (isset($this->resolvingChannels[$channelName])) {
                $errors[] = "Skipped circular reference to channel '{$channelName}'";
                continue;
            }

            try {
                // Check if channel exists in config
                if (!isset($this->config['channels'][$channelName])) {
                    $errors[] = "Channel '{$channelName}' not found in configuration";
                    continue;
                }

                // Use channel() which creates and caches loggers
                $loggers[] = $this->channel($channelName);
            } catch (\Throwable $e) {
                $errors[] = "Error creating logger for channel '{$channelName}': " . $e->getMessage();
            }
        }

        // If we had errors, log them
        if (!empty($errors)) {
            foreach ($errors as $error) {
                error_log("[LogManager] GroupLogger error: " . $error);
            }
        }

        // Create the group logger with the collected channel loggers
        return new GroupLogger(
            $loggers,
            $config['level'] ?? LogLevel::DEBUG,
            $formatter
        );
    }

    /**
     * Create a custom logger using "via" class
     *
     * @param array $config
     * @return LoggerInterface
     */
    protected function createCustomLogger(array $config): LoggerInterface
    {
        $via = $config['via'];

        // If it's a class name, instantiate it
        if (is_string($via)) {
            // Check if class exists
            if (!class_exists($via)) {
                throw new \InvalidArgumentException("Custom logger class '{$via}' does not exist");
            }

            // Try to resolve from app container if available
            if (function_exists('app') && app()->has($via)) {
                $instance = app()->make($via);
            } else {
                $instance = new $via();
            }

            // If the instance has a __invoke method, call it with the config
            if (method_exists($instance, '__invoke')) {
                return $instance($config);
            }

            // If the instance is already a logger, return it
            if ($instance instanceof LoggerInterface) {
                return $instance;
            }

            throw new \InvalidArgumentException("Custom logger class '{$via}' must be invokable or implement LoggerInterface");
        }

        // If it's already a logger instance, return it
        if ($via instanceof LoggerInterface) {
            return $via;
        }

        // If it's a callable, invoke it with the config
        if (is_callable($via)) {
            $logger = $via($config);

            if (!$logger instanceof LoggerInterface) {
                throw new \InvalidArgumentException("Custom logger callable must return a LoggerInterface instance");
            }

            return $logger;
        }

        throw new \InvalidArgumentException("Invalid 'via' configuration for custom logger");
    }

    /**
     * Call a custom creator for a driver
     *
     * @param string $driver
     * @param array $config
     * @return LoggerInterface
     */
    protected function callCustomCreator(string $driver, array $config): LoggerInterface
    {
        if (!isset($this->customCreators[$driver])) {
            throw new \InvalidArgumentException("No custom creator registered for driver '{$driver}'");
        }

        try {
            $logger = $this->customCreators[$driver]($config);

            if (!$logger instanceof LoggerInterface) {
                throw new \InvalidArgumentException("Custom creator for '{$driver}' must return a LoggerInterface instance");
            }

            return $logger;
        } catch (\Throwable $e) {
            error_log("Error creating custom logger for driver '{$driver}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Register a custom driver creator
     *
     * @param string $driver
     * @param callable $callback
     * @return self
     */
    public function extend(string $driver, callable $callback): self
    {
        $this->customCreators[$driver] = $callback;
        $this->customCreators[$driver] = $callback;

        // Debug message to help with troubleshooting
        if ($this->debug) {
            error_log("Registered custom log driver: '{$driver}'");
        }

        return $this;
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
        // Make sure we have a path
        if (!isset($config['path'])) {
            throw new \InvalidArgumentException("File logger requires a 'path' configuration value");
        }

        // Get the path and handle path placeholders like storage_path()
        $path = $config['path'];

        // If the path starts with a function name, try to resolve it
        if (preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\(/', $path, $matches)) {
            $function = $matches[1];
            if (function_exists($function)) {
                // Extract the arguments
                preg_match('/^[^(]*\(([^)]*)\)/', $path, $argMatches);
                $argString = $argMatches[1] ?? '';

                // Parse the arguments
                $args = [];
                if (!empty($argString)) {
                    // Split by commas outside of quotes
                    $argParts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $argString);

                    // Process each argument
                    foreach ($argParts as $arg) {
                        $arg = trim($arg);
                        // Remove quotes
                        $arg = preg_replace('/^[\'"]|[\'"]$/', '', $arg);
                        $args[] = $arg;
                    }
                }

                // Call the function with the arguments
                $path = call_user_func_array($function, $args);
            }
        }

        // Add date suffix for daily files
        if (strpos($path, 'daily-') !== false) {
            $path = str_replace('daily-', 'daily-' . date('Y-m-d') . '-', $path);
        }

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
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
     * Get available channel names
     *
     * @return array
     */
    public function getChannels(): array
    {
        return array_keys($this->config['channels']);
    }

    /**
     * Check if a channel exists
     *
     * @param string $channel
     * @return bool
     */
    public function hasChannel(string $channel): bool
    {
        return isset($this->config['channels'][$channel]);
    }

    /**
     * Add a new channel configuration
     *
     * @param string $channel
     * @param array $config
     * @return self
     */
    public function addChannel(string $channel, array $config): self
    {
        $this->config['channels'][$channel] = $config;

        // If this channel was already resolved, clear it so it will be recreated next time
        if (isset($this->loggers[$channel])) {
            unset($this->loggers[$channel]);
        }

        return $this;
    }

    /**
     * Get the custom creators
     *
     * @return array
     */
    public function getCustomCreators(): array
    {
        return array_keys($this->customCreators);
    }
}