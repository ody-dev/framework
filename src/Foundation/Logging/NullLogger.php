<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Logging;

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