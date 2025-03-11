<?php

namespace Ody\Core\Foundation\Logging;

/**
 * Null Logger
 * Logs messages nowhere (useful for testing or disabling logging)
 */
class NullLogger extends AbstractLogger
{
    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        // Do nothing
    }
}