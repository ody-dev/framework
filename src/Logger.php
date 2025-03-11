<?php

namespace Ody\Core;

class Logger
{
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';

    private string $logFile;
    private string $minLevel;

    public function __construct(string $logFile = 'api.log', string $minLevel = self::LEVEL_INFO)
    {
        $this->logFile = $logFile;
        $this->minLevel = $minLevel;
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $levelPriority = [
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3
        ];

        if ($levelPriority[$level] >= $levelPriority[$this->minLevel]) {
            $timestamp = date('Y-m-d H:i:s');
            $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
            $logMessage = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;

            file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
}