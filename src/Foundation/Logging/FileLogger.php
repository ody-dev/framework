<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Core\Foundation\Logging;

use Psr\Log\LogLevel;

/**
 * File Logger
 * Logs messages to a file
 */
class FileLogger extends AbstractLogger
{
    /**
     * @var string Log file path
     */
    protected string $filePath;

    /**
     * @var bool Whether to rotate logs
     */
    protected bool $rotate = false;

    /**
     * @var int Maximum file size in bytes before rotation
     */
    protected int $maxFileSize = 10485760; // 10MB

    /**
     * Constructor
     *
     * @param string $filePath
     * @param string $level
     * @param FormatterInterface|null $formatter
     * @param bool $rotate
     * @param int $maxFileSize
     */
    public function __construct(
        string $filePath,
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null,
        bool $rotate = false,
        int $maxFileSize = 10485760
    ) {
        parent::__construct($level, $formatter);

        $this->filePath = $filePath;
        $this->rotate = $rotate;
        $this->maxFileSize = $maxFileSize;

        // Ensure directory exists
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        if ($this->rotate && file_exists($this->filePath) && filesize($this->filePath) > $this->maxFileSize) {
            $this->rotateLogFile();
        }

        file_put_contents($this->filePath, $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * Rotate log file
     *
     * @return void
     */
    protected function rotateLogFile(): void
    {
        $info = pathinfo($this->filePath);
        $rotatedFile = sprintf(
            '%s/%s-%s.%s',
            $info['dirname'],
            $info['filename'],
            date('Y-m-d-H-i-s'),
            $info['extension']
        );

        rename($this->filePath, $rotatedFile);
    }
}