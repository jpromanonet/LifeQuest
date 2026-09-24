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
                  task_kind ENUM('work','personal') NOT NULL DEFAULT 'personal',
                  notes TEXT NULL,
                  image_path VARCHAR(255) NULL,
                  start_time TIME NULL,
                  estimated_minutes INT UNSIGNED NULL,
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
        } else {
            $col = $pdo->query("SHOW COLUMNS FROM weekly_tasks LIKE 'image_path'")->fetch();
            if (!$col) {
                $pdo->exec('ALTER TABLE weekly_tasks ADD COLUMN image_path VARCHAR(255) NULL AFTER notes');
            }
        }

        $timeCol = $pdo->query("SHOW COLUMNS FROM weekly_tasks LIKE 'start_time'")->fetch();
        if (!$timeCol) {
            $pdo->exec('ALTER TABLE weekly_tasks ADD COLUMN start_time TIME NULL AFTER image_path');
        }
        $minsCol = $pdo->query("SHOW COLUMNS FROM weekly_tasks LIKE 'estimated_minutes'")->fetch();
        if (!$minsCol) {
            $pdo->exec('ALTER TABLE weekly_tasks ADD COLUMN estimated_minutes INT UNSIGNED NULL AFTER start_time');
        }
        $kindCol = $pdo->query("SHOW COLUMNS FROM weekly_tasks LIKE 'task_kind'")->fetch();
        if (!$kindCol) {
            $pdo->exec(
                "ALTER TABLE weekly_tasks
                 ADD COLUMN task_kind ENUM('work','personal') NOT NULL DEFAULT 'personal' AFTER title"
            );
        }

        $stepsExists = $pdo->query("SHOW TABLES LIKE 'weekly_task_steps'")->fetch();
        if (!$stepsExists) {
            $pdo->exec(
                "CREATE TABLE weekly_task_steps (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  user_id BIGINT UNSIGNED NOT NULL,
                  task_id BIGINT UNSIGNED NOT NULL,
                  title VARCHAR(255) NOT NULL,
                  is_done TINYINT(1) NOT NULL DEFAULT 0,
                  sort_order INT NOT NULL DEFAULT 0,
                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  deleted_at DATETIME NULL,
                  KEY idx_task_steps_task (task_id, deleted_at, sort_order),
                  KEY idx_task_steps_user (user_id, deleted_at),
                  CONSTRAINT fk_task_steps_user FOREIGN KEY (user_id) REFERENCES users(id),
                  CONSTRAINT fk_task_steps_task FOREIGN KEY (task_id) REFERENCES weekly_tasks(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $done = true;
    }

    /**
     * Adjunta a cada tarea sus pasos (checklist) y contadores.
     *
     * @param list<array<string,mixed>> $tasks
     * @return list<array<string,mixed>>
     */
    private function attachSteps(int $userId, array $tasks): array
    {
        if ($tasks === []) {
            return $tasks;
        }
        $ids = array_map(static fn (array $t): int => (int) $t['id'], $tasks);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT id, task_id, title, is_done, sort_order
             FROM weekly_task_steps
             WHERE user_id = ? AND deleted_at IS NULL AND task_id IN ($placeholders)
             ORDER BY task_id ASC, sort_order ASC, id ASC"
        );
        $stmt->execute([$userId, ...$ids]);

        $byTask = [];
        foreach ($stmt->fetchAll() as $step) {
            $byTask[(int) $step['task_id']][] = $step;
        }

        foreach ($tasks as &$task) {
            $steps = $byTask[(int) $task['id']] ?? [];
            $task['steps'] = $steps;
            $task['steps_total'] = count($steps);
            $task['steps_done'] = count(array_filter($steps, static fn (array $s): bool => (int) $s['is_done'] === 1));
        }
        unset($task);
        return $tasks;
    }

    public function addStep(int $userId, int $taskId, string $title): int
    {
        $this->ensureTable();
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('El texto del paso es obligatorio.');
        }
        if ($this->find($userId, $taskId) === null) {
            throw new RuntimeException('Tarea no encontrada.');
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM weekly_task_steps
             WHERE task_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$taskId]);
        $sort = (int) $stmt->fetchColumn();

        $ins = $pdo->prepare(
            'INSERT INTO weekly_task_steps (user_id, task_id, title, sort_order)
             VALUES (:uid, :tid, :title, :sort)'
        );
        $ins->execute([
            'uid' => $userId,
            'tid' => $taskId,
            'title' => mb_substr($title, 0, 255),
            'sort' => $sort,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** @return array{step_id:int,is_done:bool,task_id:int,steps_done:int,steps_total:int} */
    public function toggleStep(int $userId, int $stepId): array
    {
        $this->ensureTable();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, task_id, is_done FROM weekly_task_steps
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $stepId, 'uid' => $userId]);
        $step = $stmt->fetch();
        if (!$step) {
            throw new RuntimeException('Paso no encontrado.');
        }
        $next = (int) $step['is_done'] === 1 ? 0 : 1;
        $pdo->prepare(
            'UPDATE weekly_task_steps SET is_done = :done, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid'
        )->execute(['done' => $next, 'id' => $stepId, 'uid' => $userId]);

        $taskId = (int) $step['task_id'];
        $count = $pdo->prepare(
            'SELECT COUNT(*) AS total, SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) AS done
             FROM weekly_task_steps WHERE task_id = ? AND deleted_at IS NULL'
        );
        $count->execute([$taskId]);
        $totals = $count->fetch();

        return [
            'step_id' => $stepId,
            'is_done' => $next === 1,
            'task_id' => $taskId,
            'steps_done' => (int) ($totals['done'] ?? 0),
            'steps_total' => (int) ($totals['total'] ?? 0),
        ];
    }

    /** @return int task_id del paso eliminado */
    public function deleteStep(int $userId, int $stepId): int
    {
        $this->ensureTable();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT task_id FROM weekly_task_steps
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $stepId, 'uid' => $userId]);
        $taskId = $stmt->fetchColumn();
        if ($taskId === false) {
            throw new RuntimeException('Paso no encontrado.');
        }
        $pdo->prepare(
            'UPDATE weekly_task_steps SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid'
        )->execute(['id' => $stepId, 'uid' => $userId]);
        return (int) $taskId;
    }

    /** Actualiza la imagen de la tarea y devuelve la ruta anterior (para borrar el archivo). */
    public function setImage(int $userId, int $taskId, ?string $path): ?string
    {
        $this->ensureTable();
        $task = $this->find($userId, $taskId);
        if ($task === null) {
            throw new RuntimeException('Tarea no encontrada.');
        }
        $previous = (string) ($task['image_path'] ?? '');
        Database::pdo()->prepare(
            'UPDATE weekly_tasks SET image_path = :path, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        )->execute(['path' => $path, 'id' => $taskId, 'uid' => $userId]);
        return $previous !== '' ? $previous : null;
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
     *   total:int,done:int,percent:float,complete:bool,
     *   minutes_total:int,minutes_done:int,tasks:list<array>
     * }>
     */
    public function weekBoard(int $userId, ?DateTimeImmutable $ref = null): array
    {
        $this->ensureTable();
        $bounds = $this->weekBounds($ref);
        $monday = $bounds['monday'];
        $today = now_local()->format('Y-m-d');

        $stmt = Database::pdo()->prepare(
            'SELECT id, task_date, title, task_kind, notes, image_path, start_time, estimated_minutes, is_done, sort_order, completed_at
             FROM weekly_tasks
             WHERE user_id = :uid AND deleted_at IS NULL
               AND task_date BETWEEN :from AND :to
             ORDER BY task_date ASC, sort_order ASC, start_time IS NULL ASC, start_time ASC, id ASC'
        );
        $stmt->execute([
            'uid' => $userId,
            'from' => $monday->format('Y-m-d'),
            'to' => $bounds['sunday']->format('Y-m-d'),
        ]);
        $rows = $this->attachSteps($userId, $stmt->fetchAll());

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
            $mins = self::minutesFromTasks($tasks);
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
                'minutes_total' => $mins['total'],
                'minutes_done' => $mins['done'],
                'tasks' => $tasks,
            ];
        }
        return $board;
    }

    /**
     * Títulos (normalizados) por día ISO de la semana, para no repetir una tarea donde ya está.
     *
     * @param list<array<string, mixed>> $board
     * @return array<string, list<string>>
     */
    public static function titleIndexFromBoard(array $board): array
    {
        $index = [];
        for ($d = 1; $d <= 7; $d++) {
            $index[(string) $d] = [];
        }
        foreach ($board as $day) {
            $dow = (string) (int) ($day['dow'] ?? 0);
            if (!isset($index[$dow])) {
                continue;
            }
            foreach ($day['tasks'] ?? [] as $task) {
                $key = self::normalizeTitleKey((string) ($task['title'] ?? ''));
                if ($key !== '') {
                    $index[$dow][] = $key;
                }
            }
        }
        return $index;
    }

    public static function normalizeTitleKey(string $title): string
    {
        return mb_strtolower(trim($title), 'UTF-8');
    }

    /**
     * Minutos estimados cargados vs. tildados.
     *
     * @param list<array<string, mixed>> $tasks
     * @return array{total:int,done:int}
     */
    public static function minutesFromTasks(array $tasks): array
    {
        $total = 0;
        $done = 0;
        foreach ($tasks as $task) {
            $minutes = (int) ($task['estimated_minutes'] ?? 0);
            if ($minutes < 1) {
                continue;
            }
            $total += $minutes;
            if ((int) ($task['is_done'] ?? 0) === 1) {
                $done += $minutes;
            }
        }
        return ['total' => $total, 'done' => $done];
    }

    /**
     * Fechas de la semana (lunes–domingo de $monday) que ya tienen esa tarea.
     *
     * @return array<string, true>
     */
    private function datesWithTitle(int $userId, DateTimeImmutable $monday, string $title): array
    {
        $key = self::normalizeTitleKey($title);
        if ($key === '') {
            return [];
        }
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT task_date, title FROM weekly_tasks
             WHERE user_id = :uid AND deleted_at IS NULL
               AND task_date BETWEEN :from AND :to'
        );
        $stmt->execute([
            'uid' => $userId,
            'from' => $monday->format('Y-m-d'),
            'to' => $monday->modify('+6 days')->format('Y-m-d'),
        ]);
        $dates = [];
        foreach ($stmt->fetchAll() as $row) {
            if (self::normalizeTitleKey((string) ($row['title'] ?? '')) === $key) {
                $dates[(string) $row['task_date']] = true;
            }
        }
        return $dates;
    }

    /** @return list<array<string,mixed>> */
    public function listForDate(int $userId, string $date): array
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT id, task_date, title, task_kind, notes, image_path, start_time, estimated_minutes, is_done, sort_order, completed_at
             FROM weekly_tasks
             WHERE user_id = :uid AND deleted_at IS NULL AND task_date = :d
             ORDER BY sort_order ASC, start_time IS NULL ASC, start_time ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId, 'd' => $date]);
        return $this->attachSteps($userId, $stmt->fetchAll());
    }

    /** @param array{title:string,task_date:string,notes?:?string,repeat_days?:mixed} $data */
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

        $notes = $data['notes'] ?? null;
        $kind = self::normalizeKind($data['task_kind'] ?? 'personal');
        $startTime = self::normalizeTime($data['start_time'] ?? null);
        $estimated = self::normalizeMinutes($data['estimated_minutes'] ?? null);
        $weekdays = self::normalizeWeekdays($data['repeat_days'] ?? []);

        $dates = [$date];
        if ($weekdays !== []) {
            $monday = $this->weekBounds(new DateTimeImmutable($date, now_local()->getTimezone()))['monday'];
            $occupied = $this->datesWithTitle($userId, $monday, $title);
            $unique = [$date => $date];
            foreach ($weekdays as $dow) {
                $copyDate = $monday->modify('+' . ($dow - 1) . ' days')->format('Y-m-d');
                if ($copyDate === $date || isset($occupied[$copyDate])) {
                    continue;
                }
                $unique[$copyDate] = $copyDate;
            }
            $dates = array_values($unique);
        }

        $lastId = 0;
        foreach ($dates as $taskDate) {
            $lastId = $this->insertTask($userId, [
                'task_date' => $taskDate,
                'title' => $title,
                'task_kind' => $kind,
                'notes' => $notes,
                'start_time' => $startTime,
                'estimated_minutes' => $estimated,
            ]);
        }
        return $lastId;
    }

    /**
     * @param array{task_date:string,title:string,notes?:?string,start_time?:?string,estimated_minutes?:?int} $row
     */
    private function insertTask(int $userId, array $row): int
    {
        $pdo = Database::pdo();
        $date = (string) $row['task_date'];
        $orderStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM weekly_tasks
             WHERE user_id = :uid AND task_date = :d AND deleted_at IS NULL'
        );
        $orderStmt->execute(['uid' => $userId, 'd' => $date]);
        $sort = (int) $orderStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO weekly_tasks (user_id, task_date, title, task_kind, notes, image_path, start_time, estimated_minutes, sort_order)
             VALUES (:uid, :d, :title, :kind, :notes, :image, :start_time, :estimated, :sort)'
        );
        $stmt->execute([
            'uid' => $userId,
            'd' => $date,
            'title' => mb_substr((string) $row['title'], 0, 255),
            'kind' => self::normalizeKind($row['task_kind'] ?? 'personal'),
            'notes' => $row['notes'] ?? null,
            'image' => $row['image_path'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'estimated' => $row['estimated_minutes'] ?? null,
            'sort' => $sort,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function normalizeKind(mixed $raw): string
    {
        return (string) $raw === 'work' ? 'work' : 'personal';
    }

    /** @return list<int> */
    private static function normalizeWeekdays(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $days = [];
        foreach ($raw as $value) {
            $n = (int) $value;
            if ($n >= 1 && $n <= 7) {
                $days[$n] = $n;
            }
        }
        ksort($days);
        return array_values($days);
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
        $kind = array_key_exists('task_kind', $data)
            ? self::normalizeKind($data['task_kind'])
            : self::normalizeKind($task['task_kind'] ?? 'personal');
        $startTime = array_key_exists('start_time', $data)
            ? self::normalizeTime($data['start_time'])
            : ($task['start_time'] ?? null);
        $estimated = array_key_exists('estimated_minutes', $data)
            ? self::normalizeMinutes($data['estimated_minutes'])
            : (isset($task['estimated_minutes']) ? (int) $task['estimated_minutes'] : null);

        $stmt = Database::pdo()->prepare(
            'UPDATE weekly_tasks
             SET title = :title, task_kind = :kind, notes = :notes, task_date = :d,
                 start_time = :start_time, estimated_minutes = :estimated,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute([
            'title' => mb_substr($title, 0, 255),
            'kind' => $kind,
            'notes' => $notes,
            'd' => $date,
            'start_time' => $startTime,
            'estimated' => $estimated,
            'id' => $id,
            'uid' => $userId,
        ]);

        $this->copyToWeekdays($userId, $id, $data['repeat_days'] ?? []);
    }

    /** Copia la tarea a otros días de la misma semana (sin tocar esta instancia). */
    private function copyToWeekdays(int $userId, int $taskId, mixed $repeatDays): void
    {
        $weekdays = self::normalizeWeekdays($repeatDays);
        if ($weekdays === []) {
            return;
        }
        $source = $this->find($userId, $taskId);
        if ($source === null) {
            return;
        }
        $attached = $this->attachSteps($userId, [$source]);
        $source = $attached[0];
        $monday = $this->weekBounds(new DateTimeImmutable((string) $source['task_date'], now_local()->getTimezone()))['monday'];
        $origin = (string) $source['task_date'];
        $occupied = $this->datesWithTitle($userId, $monday, (string) $source['title']);

        foreach ($weekdays as $dow) {
            $copyDate = $monday->modify('+' . ($dow - 1) . ' days')->format('Y-m-d');
            if ($copyDate === $origin || isset($occupied[$copyDate])) {
                continue;
            }
            $newId = $this->insertTask($userId, [
                'task_date' => $copyDate,
                'title' => (string) $source['title'],
                'task_kind' => $source['task_kind'] ?? 'personal',
                'notes' => $source['notes'] ?? null,
                'image_path' => $source['image_path'] ?? null,
                'start_time' => $source['start_time'] ?? null,
                'estimated_minutes' => isset($source['estimated_minutes']) && $source['estimated_minutes'] !== null && $source['estimated_minutes'] !== ''
                    ? (int) $source['estimated_minutes']
                    : null,
            ]);
            foreach ($source['steps'] ?? [] as $step) {
                $this->addStep($userId, $newId, (string) $step['title']);
            }
        }
    }

    private static function normalizeTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m)) {
            throw new InvalidArgumentException('Horario inválido.');
        }
        return $m[1] . ':' . $m[2] . ':00';
    }

    private static function normalizeMinutes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int) $value;
        if ($n < 1 || $n > 1440) {
            throw new InvalidArgumentException('El tiempo estimado es en minutos (1 a 1440).');
        }
        return $n;
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

    /**
     * Actualiza tipo y orden de las tareas de un día.
     *
     * @param list<array{id:int,kind:string}> $items
     */
    public function reorder(int $userId, array $items): void
    {
        $this->ensureTable();
        if ($items === []) {
            return;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE weekly_tasks
             SET task_kind = :kind, sort_order = :sort, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $sort = 0;
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $sort++;
            $stmt->execute([
                'kind' => self::normalizeKind($item['kind'] ?? 'personal'),
                'sort' => $sort,
                'id' => $id,
                'uid' => $userId,
            ]);
        }
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
     *   days_planned:int,days_complete:int,today_total:int,today_done:int,today_percent:float,
     *   week_minutes_total:int,week_minutes_done:int,today_minutes_total:int,today_minutes_done:int
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
        $weekMinutesTotal = 0;
        $weekMinutesDone = 0;
        $todayMinutesTotal = 0;
        $todayMinutesDone = 0;
        foreach ($board as $day) {
            $weekTotal += $day['total'];
            $weekDone += $day['done'];
            $weekMinutesTotal += (int) ($day['minutes_total'] ?? 0);
            $weekMinutesDone += (int) ($day['minutes_done'] ?? 0);
            if ($day['total'] > 0) {
                $daysPlanned++;
            }
            if ($day['complete']) {
                $daysComplete++;
            }
            if ($day['is_today']) {
                $todayTotal = $day['total'];
                $todayDone = $day['done'];
                $todayMinutesTotal = (int) ($day['minutes_total'] ?? 0);
                $todayMinutesDone = (int) ($day['minutes_done'] ?? 0);
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
            'week_minutes_total' => $weekMinutesTotal,
            'week_minutes_done' => $weekMinutesDone,
            'today_minutes_total' => $todayMinutesTotal,
            'today_minutes_done' => $todayMinutesDone,
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
