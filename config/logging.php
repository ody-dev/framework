<?php

use Ody\Core\Foundation\Logger;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */
    'default' => env('LOG_CHANNEL', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Log Level
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default log level for your application.
    | Available options: DEBUG, INFO, WARNING, ERROR
    |
    */
    'level' => env('LOG_LEVEL', Logger::LEVEL_INFO),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application.
    |
    */
    'channels' => [
        'file' => [
            'path' => env('LOG_FILE', 'storage/logs/'),
        ],

        // Add other log channels as needed
    ],
];