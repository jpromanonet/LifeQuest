<?php

declare(strict_types=1);

$appConfig = require dirname(__DIR__) . '/config/app.php';
$dbConfig = require dirname(__DIR__) . '/config/database.php';

date_default_timezone_set($appConfig['timezone']);

if ($appConfig['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Support/MonthProgress.php';

require_once __DIR__ . '/Services/AuditService.php';
require_once __DIR__ . '/Services/AreaService.php';
require_once __DIR__ . '/Services/AnnualPlanService.php';
require_once __DIR__ . '/Services/HabitService.php';
require_once __DIR__ . '/Services/GoalService.php';
require_once __DIR__ . '/Services/BookService.php';
require_once __DIR__ . '/Services/WeeklyPlanService.php';
require_once __DIR__ . '/Services/MetricCatalog.php';
require_once __DIR__ . '/Services/MetricsService.php';
require_once __DIR__ . '/Services/SeedManifest.php';
require_once __DIR__ . '/Services/SeedService.php';

foreach ([
    'AuthController',
    'TodayController',
    'GoalsController',
    'HabitsController',
    'BooksController',
    'WeeklyPlanController',
    'MetricsController',
    'ReviewsController',
    'HorizonController',
    'ArchiveController',
    'SettingsController',
] as $controller) {
    require_once __DIR__ . '/Controllers/' . $controller . '.php';
}

if (PHP_SAPI !== 'cli') {
    Auth::startSession($appConfig['session_name']);
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

try {
    Database::connect($dbConfig);
} catch (Throwable $e) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'DB error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    $installUrl = (function_exists('base_path') ? base_path() : '') . '/install.php';
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>LifeQuest</title></head><body style="font-family:sans-serif;padding:2rem">';
    echo '<h1>LifeQuest aún no está instalado</h1>';
    echo '<p>No se pudo conectar a MySQL. Ejecutá el instalador:</p>';
    echo '<p><a href="' . htmlspecialchars($installUrl, ENT_QUOTES, 'UTF-8') . '">Abrir install.php</a></p>';
    if (!empty($appConfig['debug'])) {
        echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</body></html>';
    exit;
}
