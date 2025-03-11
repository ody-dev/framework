<?php

namespace Ody\Core\Foundation\Logging;

use Psr\Log\LogLevel;

/**
 * Stream Logger
 * Logs messages to a stream (stdout, stderr, etc.)
 */
class StreamLogger extends AbstractLogger
{
    /**
     * @var resource Stream resource
     */
    protected $stream;

    /**
     * @var bool Whether to close the stream on destruct
     */
    protected bool $closeOnDestruct = false;

    /**
     * Constructor
     *
     * @param mixed $stream Stream resource or string (e.g., 'php://stdout')
     * @param string $level
     * @param FormatterInterface|null $formatter
     * @param bool $closeOnDestruct
     */
    public function __construct(
        $stream,
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null,
        bool $closeOnDestruct = false
    ) {
        parent::__construct($level, $formatter);

        if (is_resource($stream)) {
            $this->stream = $stream;
        } elseif (is_string($stream)) {
            $this->stream = fopen($stream, 'a');
            $this->closeOnDestruct = true;
        } else {
            throw new \InvalidArgumentException('Stream must be a resource or a string');
        }

        if (!is_resource($this->stream)) {
            throw new \RuntimeException('Failed to open stream');
        }
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->closeOnDestruct && is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        fwrite($this->stream, $message . PHP_EOL);
    }
}
