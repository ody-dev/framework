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
 * Callable Logger
 * Logs messages using a callable handler (useful for custom log handling)
 */
class CallableLogger extends AbstractLogger
{
    /**
     * @var callable Handler function
     */
    protected $handler;

    /**
     * Constructor
     *
     * @param callable $handler
     * @param string $level
     * @param FormatterInterface|null $formatter
     */
    public function __construct(
        callable $handler,
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null
    ) {
        parent::__construct($level, $formatter);

        $this->handler = $handler;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        call_user_func($this->handler, $level, $message, $context);
    }
}