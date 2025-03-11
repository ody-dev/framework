<?php

namespace Ody\Core\Foundation\Logging;

/**
 * Formatter Interface
 */
interface FormatterInterface
{
    /**
     * Format a log message
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    public function format(string $level, string $message, array $context = []): string;
}