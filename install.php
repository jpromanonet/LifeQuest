<?php

declare(strict_types=1);

/**
 * Instalador LifeQuest: crea la base, aplica schema.sql y siembra los datos de ejemplo.
 * Abrir una sola vez en el navegador: <APP_URL>/install.php
 */

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/Services/SeedManifest.php';
require_once __DIR__ . '/app/Services/HabitService.php';
require_once __DIR__ . '/app/Services/SeedService.php';
require_once __DIR__ . '/app/Services/FriendService.php';
require_once __DIR__ . '/app/Services/MilestoneService.php';

$appConfig = require __DIR__ . '/config/app.php';
$dbConfig = require __DIR__ . '/config/database.php';
date_default_timezone_set($appConfig['timezone']);

$messages = [];
$ok = false;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $server = Database::connectServer($dbConfig);
        $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $dbConfig['name']) ?: 'lifequest';
        $server->exec(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $messages[] = "Base `{$dbName}` lista.";

        Database::connect($dbConfig);
        $pdo = Database::pdo();

        $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('No se pudo leer sql/schema.sql');
        }

        $parts = explode(';', $schema);
        $applied = 0;
        foreach ($parts as $part) {
            $lines = preg_split("/\r\n|\n|\r/", $part) ?: [];
            $clean = [];
            foreach ($lines as $line) {
                $trim = trim($line);
                if ($trim === '' || str_starts_with($trim, '--')) {
                    continue;
                }
                $clean[] = $line;
            }
            $statement = trim(implode("\n", $clean));
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
            $applied++;
        }
        $messages[] = "Esquema aplicado ({$applied} sentencias).";

        $report = SeedService::run($pdo);
        $messages[] = 'Datos de ejemplo sembrados: '
            . $report['areas'] . ' áreas, '
            . $report['annual_plans'] . ' planes anuales, '
            . $report['goals'] . ' objetivos y '
            . $report['habits'] . ' hábitos.';
        $seedUser = SeedManifest::data()['user'];
        $messages[] = 'Usuario: ' . $seedUser['email'] . ' / ' . $seedUser['password'];
        $ok = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$already = false;
try {
    Database::connect($dbConfig);
    $chk = Database::pdo()->query("SHOW TABLES LIKE 'users'");
    $already = $chk && $chk->fetch() !== false;
} catch (Throwable) {
    $already = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalar LifeQuest</title>
    <style>
        :root { color-scheme: light; --bg:#F7F7FB; --card:#fff; --text:#2E3142; --muted:#73778C; --primary:#7C83E1; --border:#E5E7F2; --ok:#4FAF8E; --danger:#D97786; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Manrope, Segoe UI, sans-serif; background: var(--bg); color: var(--text); min-height:100vh; display:grid; place-items:center; padding:24px; }
        .card { width:min(560px,100%); background:var(--card); border:1px solid var(--border); border-radius:16px; padding:28px; box-shadow:0 8px 24px rgb(46 49 66 / 7%); }
        h1 { margin:0 0 8px; font-size:1.6rem; }
        p { color:var(--muted); line-height:1.5; }
        button { background:var(--primary); color:#fff; border:0; border-radius:12px; padding:12px 18px; font-weight:700; cursor:pointer; width:100%; font-size:1rem; }
        button:hover { filter:brightness(.95); }
        .msg { margin-top:12px; padding:10px 12px; border-radius:10px; background:#E1F4EE; color:#1f6b55; }
        .err { margin-top:12px; padding:10px 12px; border-radius:10px; background:#FCEAE5; color:#8a3a45; }
        ul { padding-left:1.2rem; color:var(--muted); }
        a { color:var(--primary); }
    </style>
</head>
<body>
<main class="card">
    <h1>Instalar LifeQuest</h1>
    <p>Crea la base MySQL <strong><?= htmlspecialchars((string) $dbConfig['name'], ENT_QUOTES, 'UTF-8') ?></strong>, aplica el esquema y carga datos de ejemplo (áreas, objetivos y hábitos de muestra para el año anterior, el actual y el siguiente).</p>

    <?php if ($ok): ?>
        <div class="msg">
            <strong>Instalación completa.</strong>
            <ul>
                <?php foreach ($messages as $m): ?>
                    <li><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <p><a href="index.php?r=/login">Ir al login</a></p>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($already): ?>
            <p>Ya hay tablas. Podés re-ejecutar el seeder de forma idempotente (no duplica claves canónicas).</p>
        <?php endif; ?>
        <form method="post">
            <button type="submit"><?= $already ? 'Reaplicar esquema y semillas' : 'Instalar ahora' ?></button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
