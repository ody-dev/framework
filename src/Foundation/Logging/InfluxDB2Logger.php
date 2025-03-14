<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Logging;

use InfluxDB2\Client;
use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;
use InfluxDB2\WriteType;
use Psr\Log\LogLevel;
use Swoole\Coroutine;
use Throwable;

/**
 * InfluxDB 2.x Logger
 * Logs messages to InfluxDB 2.x
 */
class InfluxDB2Logger extends AbstractLogger
{
    /**
     * @var Client InfluxDB client
     */
    protected Client $client;

    /**
     * @var WriteApi InfluxDB write API
     */
    protected WriteApi $writeApi;

    /**
     * @var string Bucket to write to
     */
    protected string $bucket;

    /**
     * @var string Organization
     */
    protected string $org;

    /**
     * @var string Measurement name for logs
     */
    protected string $measurement = 'logs';

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
     * @param string $url InfluxDB URL (e.g., http://127.0.0.01:8086)
     * @param string $token InfluxDB API token
     * @param string $org InfluxDB organization
     * @param string $bucket InfluxDB bucket
     * @param string $level Minimum log level
     * @param FormatterInterface|null $formatter
     * @param array $defaultTags Default tags for all log entries
     * @param bool $useCoroutines Whether to use Swoole coroutines
     */
    public function __construct(
        string              $url,
        string              $token,
        string              $org,
        string              $bucket,
        string              $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null,
        array               $defaultTags = [],
        bool                $useCoroutines = false
    )
    {
        parent::__construct($level, $formatter);

        // Create InfluxDB 2.x client with options array
        $this->client = new Client([
            "url" => $url,
            "token" => $token,
            "bucket" => $bucket,
            "org" => $org,
            "precision" => WritePrecision::S
        ]);

        $this->bucket = $bucket;
        $this->org = $org;

        // Set default tags
        $this->defaultTags = array_merge([
            'service' => env('APP_NAME', 'ody-service'),
            'environment' => env('APP_ENV', 'production'),
        ], $defaultTags);

        // Configure whether to use coroutines
        $this->useCoroutines = $useCoroutines && extension_loaded('swoole');

        // Get write API with batching options
        $this->writeApi = $this->client->createWriteApi([
            'writeType' => WriteType::BATCHING,
            'batchSize' => 1000,
            'flushInterval' => 1000
        ]);
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
     * Destructor: ensure data is flushed
     */
    public function __destruct()
    {
        // Flush any remaining points in the buffer
        try {
            $this->writeApi->close();
        } catch (Throwable $e) {
            error_log('Error closing InfluxDB write API: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        // Create a data point for InfluxDB
        $point = Point::measurement($this->measurement)
            ->addTag('level', strtolower($level));

        // Add default tags
        foreach ($this->defaultTags as $key => $value) {
            $point->addTag($key, (string)$value);
        }

        // Add message as a field
        $point->addField('message', $message);

        // Extract error information if available
        if (isset($context['error']) && $context['error'] instanceof Throwable) {
            $error = $context['error'];
            $point->addField('error_message', $error->getMessage());
            $point->addField('error_file', $error->getFile());
            $point->addField('error_line', (string)$error->getLine());
            $point->addField('error_trace', $error->getTraceAsString());
        }

        // Add custom tags from context
        if (isset($context['tags']) && is_array($context['tags'])) {
            foreach ($context['tags'] as $key => $value) {
                $point->addTag($key, (string)$value);
            }
        }

        // Add other context fields, excluding 'tags' and 'error' which are handled separately
        foreach ($context as $key => $value) {
            if ($key !== 'tags' && $key !== 'error') {
                // Convert arrays and objects to JSON strings
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }

                // Only add scalar values as fields
                if (is_scalar($value) || is_null($value)) {
                    $point->addField($key, $value);
                }
            }
        }

        // Write the point - use coroutines if enabled
        if ($this->useCoroutines && Coroutine::getCid() >= 0) {
            // Use Swoole coroutine for non-blocking writes
            Coroutine::create(function () use ($point) {
                try {
                    $this->writeApi->write($point);
                } catch (Throwable $e) {
                    error_log('Error writing to InfluxDB: ' . $e->getMessage());
                }
            });
        } else {
            // Synchronous write
            try {
                $this->writeApi->write($point);
            } catch (Throwable $e) {
                error_log('Error writing to InfluxDB: ' . $e->getMessage());
            }
        }
    }
}