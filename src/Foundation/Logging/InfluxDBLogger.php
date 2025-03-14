<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Logging;

use InfluxDB\Client;
use InfluxDB\Point;
use InfluxDB\Database;
use Swoole\Coroutine;
use Psr\Log\LogLevel;

/**
 * InfluxDB Logger
 * Logs messages to InfluxDB
 */
class InfluxDBLogger extends AbstractLogger
{
    /**
     * @var Client InfluxDB client
     */
    protected Client $client;

    /**
     * @var Database InfluxDB database
     */
    protected Database $database;

    /**
     * @var string Measurement name for logs
     */
    protected string $measurement = 'logs';

    /**
     * @var int Batch size for log entries
     */
    protected int $batchSize = 10;

    /**
     * @var array Buffered log entries
     */
    protected array $buffer = [];

    /**
     * @var array Default tags to include with every log entry
     */
    protected array $defaultTags = [];

    /**
     * @var bool Whether to use Swoole coroutines for non-blocking writes
     */
    protected bool $useCoroutines = false;

    /**
     * Constructor
     *
     * @param string $host InfluxDB host
     * @param string $database InfluxDB database name
     * @param string $username InfluxDB username
     * @param string $password InfluxDB password
     * @param int $port InfluxDB port
     * @param string $level Minimum log level
     * @param FormatterInterface|null $formatter
     * @param array $defaultTags Default tags for all log entries
     * @param int $batchSize Size of log batches before writing
     * @param bool $useCoroutines Whether to use Swoole coroutines
     */
    public function __construct(
        string $host,
        string $database,
        string $username = '',
        string $password = '',
        int $port = 8086,
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null,
        array $defaultTags = [],
        int $batchSize = 10,
        bool $useCoroutines = false
    ) {
        parent::__construct($level, $formatter);

        // Create InfluxDB client
        $this->client = new Client($host, $port, $username, $password);
        $this->database = $this->client->selectDB($database);

        // Set default tags and batch size
        $this->defaultTags = array_merge([
            'service' => env('APP_NAME', 'ody-service'),
            'environment' => env('APP_ENV', 'production'),
        ], $defaultTags);

        $this->batchSize = $batchSize;
        $this->useCoroutines = $useCoroutines && extension_loaded('swoole');
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        // Create a point (data point) to write to InfluxDB
        $point = new Point(
            $this->measurement,
            null, // InfluxDB will use current timestamp if null
            $this->getTags($level, $context),
            $this->getFields($message, $context)
        );

        // Add to buffer
        $this->buffer[] = $point;

        // Flush buffer if it reaches the batch size
        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * Flush buffered log entries to InfluxDB
     *
     * @return void
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $points = $this->buffer;
        $this->buffer = []; // Clear buffer before writing

        if ($this->useCoroutines && Coroutine::getCid() >= 0) {
            // Use Swoole coroutine for non-blocking writes
            Coroutine::create(function () use ($points) {
                try {
                    $this->database->writePoints($points);
                } catch (\Throwable $e) {
                    error_log('Error writing to InfluxDB: ' . $e->getMessage());
                }
            });
        } else {
            // Synchronous write
            try {
                $this->database->writePoints($points);
            } catch (\Throwable $e) {
                error_log('Error writing to InfluxDB: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get tags for the log entry
     *
     * @param string $level
     * @param array $context
     * @return array
     */
    protected function getTags(string $level, array $context): array
    {
        $tags = $this->defaultTags;

        // Add log level as a tag
        $tags['level'] = strtolower($level);

        // Extract additional tags from context
        if (isset($context['tags']) && is_array($context['tags'])) {
            foreach ($context['tags'] as $key => $value) {
                // InfluxDB tags must be strings
                $tags[$key] = (string)$value;
            }
        }

        return $tags;
    }

    /**
     * Get fields for the log entry
     *
     * @param string $message
     * @param array $context
     * @return array
     */
    protected function getFields(string $message, array $context): array
    {
        $fields = ['message' => $message];

        // Extract error information if available
        if (isset($context['error']) && $context['error'] instanceof \Throwable) {
            $error = $context['error'];
            $fields['error_message'] = $error->getMessage();
            $fields['error_file'] = $error->getFile();
            $fields['error_line'] = $error->getLine();
            $fields['error_trace'] = $error->getTraceAsString();
        }

        // Add other context fields, excluding 'tags' which are handled separately
        foreach ($context as $key => $value) {
            if ($key !== 'tags' && $key !== 'error') {
                // Convert objects and arrays to strings for InfluxDB
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }

                // Ensure we have scalar values
                if (is_scalar($value) || is_null($value)) {
                    $fields[$key] = $value;
                }
            }
        }

        return $fields;
    }

    /**
     * Set the measurement name
     *
     * @param string $measurement
     * @return self
     */
    public function setMeasurement(string $measurement): self
    {
        $this->measurement = $measurement;
        return $this;
    }

    /**
     * Add default tags
     *
     * @param array $tags
     * @return self
     */
    public function addDefaultTags(array $tags): self
    {
        $this->defaultTags = array_merge($this->defaultTags, $tags);
        return $this;
    }

    /**
     * Set batch size
     *
     * @param int $size
     * @return self
     */
    public function setBatchSize(int $size): self
    {
        $this->batchSize = max(1, $size);
        return $this;
    }

    /**
     * Enable or disable coroutines
     *
     * @param bool $enable
     * @return self
     */
    public function useCoroutines(bool $enable): self
    {
        $this->useCoroutines = $enable && extension_loaded('swoole');
        return $this;
    }

    /**
     * Destructor: ensure all logs are flushed
     */
    public function __destruct()
    {
        $this->flush();
    }
}