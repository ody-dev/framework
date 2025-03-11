<?php

namespace Ody\Core\Foundation\Logging;

use Psr\Log\LogLevel;

/**
 * Abstract Logger Implementation
 * Base logger class that provides common functionality for all loggers
 */
abstract class AbstractLogger extends \Psr\Log\AbstractLogger implements LoggerInterface
{
    /**
     * @var string Current log level
     */
    protected string $level = LogLevel::DEBUG;

    /**
     * @var FormatterInterface Formatter for log messages
     */
    protected FormatterInterface $formatter;

    /**
     * @var array Log level priorities (higher = more severe)
     */
    protected array $levelPriorities = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7
    ];

    /**
     * Constructor
     *
     * @param string $level
     * @param FormatterInterface|null $formatter
     */
    public function __construct(string $level = LogLevel::DEBUG, ?FormatterInterface $formatter = null)
    {
        $this->level = $level;
        $this->formatter = $formatter ?? new LineFormatter();
    }

    /**
     * {@inheritdoc}
     */
    public function setLevel(string $level): LoggerInterface
    {
        if (!isset($this->levelPriorities[$level])) {
            throw new \InvalidArgumentException("Invalid log level: $level");
        }

        $this->level = $level;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter): LoggerInterface
    {
        $this->formatter = $formatter;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }

    /**
     * Check if the level is allowed to be logged
     *
     * @param string $level
     * @return bool
     */
    protected function isLevelAllowed(string $level): bool
    {
        return $this->levelPriorities[$level] >= $this->levelPriorities[$this->level];
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []): void
    {
        if (!$this->isLevelAllowed($level)) {
            return;
        }

        $formattedMessage = $this->formatter->format($level, $message, $context);
        $this->write($level, $formattedMessage, $context);
    }

    /**
     * Write a log message to the storage medium
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    abstract protected function write(string $level, string $message, array $context = []): void;
}