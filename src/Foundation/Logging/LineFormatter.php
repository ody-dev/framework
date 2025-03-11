<?php

namespace Ody\Core\Foundation\Logging;

/**
 * Line Formatter
 * Formats log messages as single lines with timestamp and level
 */
class LineFormatter implements FormatterInterface
{
    /**
     * @var string Line format
     */
    protected string $format = "[%datetime%] [%level%] %message% %context%";

    /**
     * @var string DateTime format
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

    /**
     * Constructor
     *
     * @param string|null $format
     * @param string|null $dateFormat
     */
    public function __construct(?string $format = null, ?string $dateFormat = null)
    {
        if ($format !== null) {
            $this->format = $format;
        }

        if ($dateFormat !== null) {
            $this->dateFormat = $dateFormat;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function format(string $level, string $message, array $context = []): string
    {
        $output = $this->format;

        // Replace placeholders
        $output = str_replace('%datetime%', date($this->dateFormat), $output);
        $output = str_replace('%level%', strtoupper($level), $output);
        $output = str_replace('%message%', $this->interpolateMessage($message, $context), $output);

        // Format context if not empty
        $contextStr = !empty($context) ? $this->formatContext($context) : '';
        $output = str_replace('%context%', $contextStr, $output);

        return $output;
    }

    /**
     * Interpolate message placeholders with context values
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    protected function interpolateMessage(string $message, array $context = []): string
    {
        // Replace {placeholders} with context values
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || method_exists($val, '__toString')) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Format context as string
     *
     * @param array $context
     * @return string
     */
    protected function formatContext(array $context): string
    {
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) {
                $context[$key] = [
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                    'trace' => $value->getTraceAsString()
                ];
            }
        }

        return json_encode($context);
    }
}