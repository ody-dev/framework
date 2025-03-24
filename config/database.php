<?php

return [
    'charset' => 'utf8mb4',
    'enable_connection_pool' => env('DB_ENABLE_POOL', false),
    'environments' => [
        'local' => [
            'adapter' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'root'),
            'db_name' => env('DB_DATABASE', 'ody'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix'    => '',
            'pool_size' => env('DB_POOL_SIZE', 64),
            'options' => [
                // PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_CASE,
                PDO::CASE_LOWER,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_DIRECT_QUERY => false,
                // Max packet size for large data transfers
                // PDO::MYSQL_ATTR_MAX_BUFFER_SIZE => 16777216, // 16MB
            ],
        ],
        'production' => [
            'adapter' => 'mysql',
            'host' => 'production_host',
            'port' => 3306,
            'username' => 'user',
            'password' => 'pass',
            'db_name' => 'my_production_db',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ],
    ],
    'default_environment' => 'local',
    'log_table_name' => 'migrations_log',
    'migration_dirs' => [
        'migrations' => 'database/migrations',
    ],
];
