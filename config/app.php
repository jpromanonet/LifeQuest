<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'name' => lq_env('APP_NAME', 'LifeQuest'),
    'env' => strtolower((string) lq_env('APP_ENV', 'local')),
    'debug' => filter_var(lq_env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) lq_env('APP_URL', ''), '/'),
    'session_name' => (string) lq_env('SESSION_NAME', 'lifequest_session'),
    'session_lifetime' => (int) lq_env('SESSION_LIFETIME', '28800'),
    'session_idle' => (int) lq_env('SESSION_IDLE', '7200'),
    'timezone' => 'America/Argentina/Buenos_Aires',
    'locale' => 'es_AR',
    'patrium_url' => rtrim((string) lq_env('PATRIUM_URL', ''), '/'),
];
