<?php

declare(strict_types=1);

/**
 * Migraciones LifeQuest: actualiza el esquema de una instalación existente
 * al último formato (áreas, progreso por meses, hábitos).
 * Abrir en el navegador: <APP_URL>/migrate.php
 */

require __DIR__ . '/app/bootstrap.php';

$messages = [];
$error = null;

try {
    $pdo = Database::pdo();

    $cols = $pdo->query("SHOW COLUMNS FROM life_areas LIKE 'scope'")->fetch();
    if (!$cols) {
        $pdo->exec(
            "ALTER TABLE life_areas
             ADD COLUMN scope ENUM('annual','horizon') NOT NULL DEFAULT 'annual' AFTER sort_order,
             ADD KEY idx_life_areas_scope (user_id, scope)"
        );
        $messages[] = 'Columna life_areas.scope agregada.';
    }

    // goals.progress_mode + months
    $modeCol = $pdo->query("SHOW COLUMNS FROM goals LIKE 'progress_mode'")->fetch();
    if ($modeCol && !str_contains((string) $modeCol['Type'], 'months')) {
        $pdo->exec(
            "ALTER TABLE goals
             MODIFY COLUMN progress_mode
             ENUM('manual','milestones','quantity','binary','linked_habits','months')
             NOT NULL DEFAULT 'months'"
        );
        $messages[] = 'goals.progress_mode incluye months.';
    }

    $goalMonths = $pdo->query("SHOW TABLES LIKE 'goal_month_checks'")->fetch();
    if (!$goalMonths) {
        $pdo->exec(
            "CREATE TABLE goal_month_checks (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              goal_id BIGINT UNSIGNED NOT NULL,
              month_num TINYINT UNSIGNED NOT NULL,
              is_checked TINYINT(1) NOT NULL DEFAULT 0,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY uq_goal_month (goal_id, month_num),
              CONSTRAINT fk_goal_month_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $messages[] = 'Tabla goal_month_checks creada.';
    }

    // habits tracking columns
    $trackCol = $pdo->query("SHOW COLUMNS FROM habits LIKE 'tracking_mode'")->fetch();
    if (!$trackCol) {
        $pdo->exec(
            "ALTER TABLE habits
             ADD COLUMN tracking_mode ENUM('months','units','daily') NOT NULL DEFAULT 'months' AFTER frequency_type,
             ADD COLUMN current_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER target_per_period,
             ADD COLUMN progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER current_value"
        );
        $messages[] = 'Columnas habits.tracking_mode / current_value / progress_percent agregadas.';
    } else {
        if (!$pdo->query("SHOW COLUMNS FROM habits LIKE 'current_value'")->fetch()) {
            $pdo->exec("ALTER TABLE habits ADD COLUMN current_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER target_per_period");
            $messages[] = 'habits.current_value agregada.';
        }
        if (!$pdo->query("SHOW COLUMNS FROM habits LIKE 'progress_percent'")->fetch()) {
            $pdo->exec("ALTER TABLE habits ADD COLUMN progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER current_value");
            $messages[] = 'habits.progress_percent agregada.';
        }
    }

    $habitMonths = $pdo->query("SHOW TABLES LIKE 'habit_month_checks'")->fetch();
    if (!$habitMonths) {
        $pdo->exec(
            "CREATE TABLE habit_month_checks (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              habit_id BIGINT UNSIGNED NOT NULL,
              year_num SMALLINT NOT NULL,
              month_num TINYINT UNSIGNED NOT NULL,
              is_checked TINYINT(1) NOT NULL DEFAULT 0,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY uq_habit_year_month (habit_id, year_num, month_num),
              CONSTRAINT fk_habit_month_habit FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $messages[] = 'Tabla habit_month_checks creada.';
    }

    $booksTable = $pdo->query("SHOW TABLES LIKE 'books'")->fetch();
    if (!$booksTable) {
        $pdo->exec(
            "CREATE TABLE books (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id BIGINT UNSIGNED NOT NULL,
              title VARCHAR(255) NOT NULL,
              year_num SMALLINT NOT NULL,
              planned_month TINYINT UNSIGNED NULL,
              finished_at DATE NULL,
              pages INT UNSIGNED NULL,
              pages_read INT UNSIGNED NOT NULL DEFAULT 0,
              status ENUM('planned','reading','finished') NOT NULL DEFAULT 'planned',
              goodreads_url VARCHAR(500) NULL,
              notes TEXT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              deleted_at DATETIME NULL,
              KEY idx_books_user_year (user_id, year_num),
              CONSTRAINT fk_books_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $messages[] = 'Tabla books creada.';
    }

    $users = $pdo->query('SELECT id FROM users')->fetchAll();
    $areas = new AreaService();
    $plans = new AnnualPlanService();
    $goals = new GoalService();

    foreach ($users as $user) {
        $uid = (int) $user['id'];
        $areas->ensureCanonicalAreas($uid);

        // Refresca solo los años que el usuario ya tiene: un año eliminado no debe volver.
        foreach ($plans->years($uid) as $year) {
            $plans->ensureYear($uid, $year);
        }

        $goalIds = $pdo->prepare(
            "SELECT id, progress_mode FROM goals WHERE user_id = :uid AND deleted_at IS NULL"
        );
        $goalIds->execute(['uid' => $uid]);
        foreach ($goalIds->fetchAll() as $row) {
            $mode = (string) ($row['progress_mode'] ?? 'months');
            if ($mode === 'binary' || $mode === 'quantity') {
                continue;
            }
            $goals->ensureMonthMode($uid, (int) $row['id']);
        }
    }

    $messages[] = 'Áreas anuales canónicas (incluye Proyectos) y horizonte aseguradas.';
    $messages[] = 'Objetivos por meses/unidades/sí-no listos.';

    $books = new BookService();
    $bookYears = 0;
    foreach ($users as $user) {
        $uid = (int) $user['id'];
        foreach ($plans->years($uid) as $planYear) {
            $books->ensureAnnualGoal($uid, $planYear);
            $bookYears++;
        }
    }
    $messages[] = sprintf('Objetivo fijo "Leer N libros en el año" (%d por defecto) asegurado en %d años.', BookService::DEFAULT_TARGET, $bookYears);

    $weeklyExists = $pdo->query("SHOW TABLES LIKE 'weekly_tasks'")->fetch();
    if (!$weeklyExists) {
        (new WeeklyPlanService())->ensureTable();
        $messages[] = 'Tabla weekly_tasks creada (Plan semanal).';
    } else {
        $messages[] = 'Tabla weekly_tasks ya existe.';
    }

    $rulesExists = $pdo->query("SHOW TABLES LIKE 'own_rules'")->fetch();
    if (!$rulesExists) {
        (new RuleService())->ensureTables();
        $messages[] = 'Tablas rule_categories y own_rules creadas (Reglas propias).';
    } else {
        $messages[] = 'Tablas de Reglas propias ya existen.';
    }

    $imgCol = $pdo->query("SHOW COLUMNS FROM weekly_tasks LIKE 'image_path'")->fetch();
    $stepsTable = $pdo->query("SHOW TABLES LIKE 'weekly_task_steps'")->fetch();
    $timeCol = $pdo->query("SHOW COLUMNS FROM weekly_tasks LIKE 'start_time'")->fetch();
    $minsCol = $pdo->query("SHOW COLUMNS FROM weekly_tasks LIKE 'estimated_minutes'")->fetch();
    if (!$imgCol || !$stepsTable || !$timeCol || !$minsCol) {
        (new WeeklyPlanService())->ensureTable();
        $messages[] = 'Tareas: imagen, checklist, horario de inicio y tiempo estimado.';
    } else {
        $messages[] = 'Imagen, checklist y horario de tareas ya migrados.';
    }

    $calCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'calendar_embed_url'")->fetch();
    if ($calCol) {
        $pdo->exec('ALTER TABLE users DROP COLUMN calendar_embed_url');
        $messages[] = 'Columna users.calendar_embed_url eliminada.';
    }

    $milestonesExists = $pdo->query("SHOW TABLES LIKE 'milestones'")->fetch();
    if (!$milestonesExists) {
        (new MilestoneService())->ensureTable();
        $messages[] = 'Tabla milestones creada (Hitos).';
    } else {
        $messages[] = 'Tabla milestones ya existe.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Migrar LifeQuest</title>
    <style>
        body{font-family:Manrope,Segoe UI,sans-serif;background:#F7F7FB;display:grid;place-items:center;min-height:100vh;margin:0}
        .card{background:#fff;border:1px solid #E5E7F2;border-radius:16px;padding:28px;max-width:560px;width:90%}
        .ok{background:#E1F4EE;color:#1f6b55;padding:10px;border-radius:10px}
        .err{background:#FCEAE5;color:#8a3a45;padding:10px;border-radius:10px}
        a{color:#7C83E1}
    </style>
</head>
<body>
<main class="card">
    <h1>Migración LifeQuest</h1>
    <?php if ($error): ?>
        <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php else: ?>
        <div class="ok">
            <ul>
                <?php foreach ($messages as $m): ?>
                    <li><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <p>
            <a href="index.php?r=/goals">Objetivos</a> ·
            <a href="index.php?r=/habits">Hábitos</a> ·
            <a href="index.php?r=/settings">Configuración</a>
        </p>
    <?php endif; ?>
</main>
</body>
</html>
