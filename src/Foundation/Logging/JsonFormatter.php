<?php

namespace Ody\Core\Foundation\Logging;

/**
 * JSON Formatter
 * Formats log messages as JSON objects
 */
class JsonFormatter implements FormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function format(string $level, string $message, array $context = []): string
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message
        ];

        // Add context to log data
        if (!empty($context)) {
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

            $logData['context'] = $context;
        }

        return json_encode($logData);
    }
}