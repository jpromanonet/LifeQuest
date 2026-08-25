<?php

declare(strict_types=1);

final class WeeklyPlanService
{
    public const DAY_LABELS = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mié',
        4 => 'Jue',
        5 => 'Vie',
        6 => 'Sáb',
        7 => 'Dom',
    ];

    public function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $pdo = Database::pdo();
        $exists = $pdo->query("SHOW TABLES LIKE 'weekly_tasks'")->fetch();
        if (!$exists) {
            $pdo->exec(
                "CREATE TABLE weekly_tasks (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  user_id BIGINT UNSIGNED NOT NULL,
                  task_date DATE NOT NULL,
                  title VARCHAR(255) NOT NULL,
                  notes TEXT NULL,
                  is_done TINYINT(1) NOT NULL DEFAULT 0,
                  sort_order INT NOT NULL DEFAULT 0,
                  completed_at DATETIME NULL,
                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  deleted_at DATETIME NULL,
                  KEY idx_weekly_tasks_user_date (user_id, task_date, deleted_at),
                  CONSTRAINT fk_weekly_tasks_user FOREIGN KEY (user_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $done = true;
    }

    /** @return array{monday:DateTimeImmutable,sunday:DateTimeImmutable} */
    public function weekBounds(?DateTimeImmutable $ref = null): array
    {
        $ref = $ref ?? now_local();
        $dow = (int) $ref->format('N');
        $monday = $ref->modify('-' . ($dow - 1) . ' days')->setTime(0, 0);
        $sunday = $monday->modify('+6 days');
        return ['monday' => $monday, 'sunday' => $sunday];
    }

    /**
     * @return list<array{
     *   date:string,dow:int,label:string,date_label:string,is_today:bool,
     *   total:int,done:int,percent:float,complete:bool,tasks:list<array>
     * }>
     */
    public function weekBoard(int $userId, ?DateTimeImmutable $ref = null): array
    {
        $this->ensureTable();
        $bounds = $this->weekBounds($ref);
        $monday = $bounds['monday'];
        $today = now_local()->format('Y-m-d');

        $stmt = Database::pdo()->prepare(
            'SELECT id, task_date, title, notes, is_done, sort_order, completed_at
             FROM weekly_tasks
             WHERE user_id = :uid AND deleted_at IS NULL
               AND task_date BETWEEN :from AND :to
             ORDER BY task_date ASC, sort_order ASC, id ASC'
        );
        $stmt->execute([
            'uid' => $userId,
            'from' => $monday->format('Y-m-d'),
            'to' => $bounds['sunday']->format('Y-m-d'),
        ]);
        $rows = $stmt->fetchAll();

        $byDate = [];
        foreach ($rows as $row) {
            $d = (string) $row['task_date'];
            $byDate[$d][] = $row;
        }

        $board = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $monday->modify('+' . $i . ' day');
            $date = $day->format('Y-m-d');
            $dow = (int) $day->format('N');
            $tasks = $byDate[$date] ?? [];
            $total = count($tasks);
            $done = 0;
            foreach ($tasks as $t) {
                if ((int) $t['is_done'] === 1) {
                    $done++;
                }
            }
            $percent = $total > 0 ? round(($done / $total) * 100, 1) : 0.0;
            $board[] = [
                'date' => $date,
                'dow' => $dow,
                'label' => self::DAY_LABELS[$dow] ?? $day->format('D'),
                'date_label' => $day->format('d/m'),
                'is_today' => $date === $today,
                'total' => $total,
                'done' => $done,
                'percent' => $percent,
                'complete' => $total > 0 && $done === $total,
                'tasks' => $tasks,
            ];
        }
        return $board;
    }

    /** @return list<array<string,mixed>> */
    public function listForDate(int $userId, string $date): array
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT id, task_date, title, notes, is_done, sort_order, completed_at
             FROM weekly_tasks
             WHERE user_id = :uid AND deleted_at IS NULL AND task_date = :d
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId, 'd' => $date]);
        return $stmt->fetchAll();
    }

    /** @param array{title:string,task_date:string,notes?:?string} $data */
    public function create(int $userId, array $data): int
    {
        $this->ensureTable();
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('El título es obligatorio.');
        }
        $date = (string) ($data['task_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Fecha inválida.');
        }

        $pdo = Database::pdo();
        $orderStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM weekly_tasks
             WHERE user_id = :uid AND task_date = :d AND deleted_at IS NULL'
        );
        $orderStmt->execute(['uid' => $userId, 'd' => $date]);
        $sort = (int) $orderStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO weekly_tasks (user_id, task_date, title, notes, sort_order)
             VALUES (:uid, :d, :title, :notes, :sort)'
        );
        $stmt->execute([
            'uid' => $userId,
            'd' => $date,
            'title' => mb_substr($title, 0, 255),
            'notes' => $data['notes'] ?? null,
            'sort' => $sort,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** @param array{title?:string,notes?:?string,task_date?:string} $data */
    public function update(int $userId, int $id, array $data): void
    {
        $this->ensureTable();
        $task = $this->find($userId, $id);
        if ($task === null) {
            throw new RuntimeException('Tarea no encontrada.');
        }

        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : (string) $task['title'];
        if ($title === '') {
            throw new InvalidArgumentException('El título es obligatorio.');
        }
        $date = array_key_exists('task_date', $data) ? (string) $data['task_date'] : (string) $task['task_date'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Fecha inválida.');
        }
        $notes = array_key_exists('notes', $data) ? $data['notes'] : $task['notes'];

        $stmt = Database::pdo()->prepare(
            'UPDATE weekly_tasks
             SET title = :title, notes = :notes, task_date = :d, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute([
            'title' => mb_substr($title, 0, 255),
            'notes' => $notes,
            'd' => $date,
            'id' => $id,
            'uid' => $userId,
        ]);
    }

    public function toggle(int $userId, int $id, ?bool $done = null): array
    {
        $this->ensureTable();
        $task = $this->find($userId, $id);
        if ($task === null) {
            throw new RuntimeException('Tarea no encontrada.');
        }
        $next = $done !== null ? ($done ? 1 : 0) : ((int) $task['is_done'] === 1 ? 0 : 1);
        $stmt = Database::pdo()->prepare(
            'UPDATE weekly_tasks
             SET is_done = :done,
                 completed_at = CASE WHEN :done2 = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute([
            'done' => $next,
            'done2' => $next,
            'id' => $id,
            'uid' => $userId,
        ]);
        return ['id' => $id, 'is_done' => $next === 1];
    }

    public function delete(int $userId, int $id): void
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'UPDATE weekly_tasks SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    /** @return array<string,mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM weekly_tasks WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array{
     *   week_total:int,week_done:int,week_percent:float,
     *   days_planned:int,days_complete:int,today_total:int,today_done:int,today_percent:float
     * }
     */
    public function weekStats(int $userId, ?DateTimeImmutable $ref = null): array
    {
        $board = $this->weekBoard($userId, $ref);
        $weekTotal = 0;
        $weekDone = 0;
        $daysPlanned = 0;
        $daysComplete = 0;
        $todayTotal = 0;
        $todayDone = 0;
        foreach ($board as $day) {
            $weekTotal += $day['total'];
            $weekDone += $day['done'];
            if ($day['total'] > 0) {
                $daysPlanned++;
            }
            if ($day['complete']) {
                $daysComplete++;
            }
            if ($day['is_today']) {
                $todayTotal = $day['total'];
                $todayDone = $day['done'];
            }
        }
        return [
            'week_total' => $weekTotal,
            'week_done' => $weekDone,
            'week_percent' => $weekTotal > 0 ? round(($weekDone / $weekTotal) * 100, 1) : 0.0,
            'days_planned' => $daysPlanned,
            'days_complete' => $daysComplete,
            'today_total' => $todayTotal,
            'today_done' => $todayDone,
            'today_percent' => $todayTotal > 0 ? round(($todayDone / $todayTotal) * 100, 1) : 0.0,
        ];
    }

    /**
     * Resumen para métricas generales (últimas N semanas + semana actual).
     *
     * @return array{current:array,avg_completion:float,perfect_days:int,tasks_done:int,tasks_total:int}
     */
    public function metricsSnapshot(int $userId, int $weeks = 4): array
    {
        $this->ensureTable();
        $bounds = $this->weekBounds();
        $from = $bounds['monday']->modify('-' . (($weeks - 1) * 7) . ' days')->format('Y-m-d');
        $to = $bounds['sunday']->format('Y-m-d');

        $stmt = Database::pdo()->prepare(
            'SELECT task_date,
                    COUNT(*) AS total,
                    SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) AS done
             FROM weekly_tasks
             WHERE user_id = :uid AND deleted_at IS NULL
               AND task_date BETWEEN :from AND :to
             GROUP BY task_date'
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        $rows = $stmt->fetchAll();

        $tasksTotal = 0;
        $tasksDone = 0;
        $perfectDays = 0;
        $dayPercents = [];
        foreach ($rows as $row) {
            $total = (int) $row['total'];
            $done = (int) $row['done'];
            $tasksTotal += $total;
            $tasksDone += $done;
            if ($total > 0 && $done === $total) {
                $perfectDays++;
            }
            if ($total > 0) {
                $dayPercents[] = ($done / $total) * 100;
            }
        }

        return [
            'current' => $this->weekStats($userId),
            'avg_completion' => $dayPercents !== [] ? round(array_sum($dayPercents) / count($dayPercents), 1) : 0.0,
            'perfect_days' => $perfectDays,
            'tasks_done' => $tasksDone,
            'tasks_total' => $tasksTotal,
        ];
    }
}
