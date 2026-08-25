<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'host' => lq_env('DB_HOST', '127.0.0.1'),
    'port' => (int) lq_env('DB_PORT', '3306'),
    'name' => lq_env('DB_NAME', 'lifequest'),
    'user' => lq_env('DB_USER', 'root'),
    'pass' => lq_env('DB_PASS', '') ?? '',
    'charset' => 'utf8mb4',
];
