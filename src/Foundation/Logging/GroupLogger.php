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

/**
 * Group Logger
 * Logs messages to multiple loggers at once
 */
class GroupLogger extends AbstractLogger
{
    /**
     * @var LoggerInterface[] Array of loggers
     */
    protected array $loggers = [];

    /**
     * Constructor
     *
     * @param LoggerInterface[] $loggers
     * @param string $level
     * @param FormatterInterface|null $formatter
     */
    public function __construct(
        array $loggers = [],
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null
    ) {
        parent::__construct($level, $formatter);

        foreach ($loggers as $logger) {
            $this->addLogger($logger);
        }
    }

    /**
     * Add a logger to the group
     *
     * @param LoggerInterface $logger
     * @return self
     */
    public function addLogger(LoggerInterface $logger): self
    {
        $this->loggers[] = $logger;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            if ($logger->getLevel() !== $this->level) {
                $logger->setLevel($this->level);
            }

            $logger->log($level, $message, $context);
        }
    }
}
