<?php

declare(strict_types=1);

final class HabitService
{
    private const VALID_STATUSES = ['completed', 'partial', 'skipped_justified', 'missed'];
    public const KEY_WATER = 'sys_water';
    public const KEY_FRUIT = 'sys_fruit';
    public const KEY_TALK_FRIEND = 'sys_talk_friend';
    private const VALID_TRACKING = ['months', 'units', 'daily', 'daily_qty'];

    /**
     * @return list<array<string,mixed>>
     */
    public static function systemCatalog(): array
    {
        return [
            ['habit_key' => self::KEY_WATER, 'name' => 'Tomar mínimo 2 litros de agua', 'tracking_mode' => 'daily_qty', 'target_per_period' => 2000, 'unit' => 'ml', 'description' => 'Vaso 250 ml o botella 500 ml hasta 2000 ml.'],
            ['habit_key' => self::KEY_FRUIT, 'name' => 'Comer 3 frutas', 'tracking_mode' => 'daily_qty', 'target_per_period' => 3, 'unit' => 'frutas', 'description' => 'Banana, mandarina u naranja · 3 por día.'],
            ['habit_key' => 'sys_care', 'name' => 'Cuidado personal (cepillar dientes, lavar cara, desodorante, etc)', 'tracking_mode' => 'daily', 'description' => null],
            ['habit_key' => 'sys_dress', 'name' => 'Vestirse', 'tracking_mode' => 'daily', 'description' => null],
            ['habit_key' => 'sys_breakfast', 'name' => 'Desayuno', 'tracking_mode' => 'daily', 'description' => null],
            ['habit_key' => 'sys_lunch', 'name' => 'Almuerzo', 'tracking_mode' => 'daily', 'description' => null],
            ['habit_key' => 'sys_snack', 'name' => 'Merienda', 'tracking_mode' => 'daily', 'description' => null],
            ['habit_key' => 'sys_dinner', 'name' => 'Cena', 'tracking_mode' => 'daily', 'description' => null],
            ['habit_key' => 'sys_go_out', 'name' => 'Salir de casa (ir a hacer mandados o dar una vuelta al parque pero salir)', 'tracking_mode' => 'daily', 'description' => null],
            ['habit_key' => self::KEY_TALK_FRIEND, 'name' => 'Hablar con algún amigo/a', 'tracking_mode' => 'daily', 'description' => 'La app sugiere con quién, sin repetir.'],
        ];
    }

    public function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $pdo = Database::pdo();
        $sysCol = $pdo->query("SHOW COLUMNS FROM habits LIKE 'is_system'")->fetch();
        if (!$sysCol) {
            $pdo->exec('ALTER TABLE habits ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER notes');
        }
        $mode = $pdo->query("SHOW COLUMNS FROM habits LIKE 'tracking_mode'")->fetch();
        $type = (string) ($mode['Type'] ?? '');
        if ($type !== '' && !str_contains($type, 'daily_qty')) {
            $pdo->exec(
                "ALTER TABLE habits
                 MODIFY COLUMN tracking_mode ENUM('months','units','daily','daily_qty') NOT NULL DEFAULT 'months'"
            );
        }
        $done = true;
    }

    public function ensureSystemHabits(int $userId): void
    {
        $this->ensureSchema();
        $pdo = Database::pdo();
        $find = $pdo->prepare(
            'SELECT id, deleted_at, archived_at, display_number
             FROM habits WHERE user_id = :uid AND habit_key = :k LIMIT 1'
        );
        $maxStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(display_number), 0) FROM habits
             WHERE user_id = :uid AND active = 1 AND deleted_at IS NULL AND archived_at IS NULL'
        );
        $maxStmt->execute(['uid' => $userId]);
        $next = (int) $maxStmt->fetchColumn();

        foreach (self::systemCatalog() as $spec) {
            $find->execute(['uid' => $userId, 'k' => $spec['habit_key']]);
            $existing = $find->fetch();
            $tracking = (string) $spec['tracking_mode'];
            $target = (int) ($spec['target_per_period'] ?? 1);
            $unit = (string) ($spec['unit'] ?? 'vez');
            if ($existing) {
                $hid = (int) $existing['id'];
                $pdo->prepare(
                    'UPDATE habits
                     SET name = :name, description = :description, is_system = 1,
                         tracking_mode = :mode, target_per_period = :target, unit = :unit,
                         frequency_type = \'daily\', active = 1,
                         start_date = NULL, end_date = NULL,
                         deleted_at = NULL, archived_at = NULL
                     WHERE id = :id'
                )->execute([
                    'name' => $spec['name'],
                    'description' => $spec['description'],
                    'mode' => $tracking,
                    'target' => $target,
                    'unit' => $unit,
                    'id' => $hid,
                ]);
                $this->replaceScheduleDays($hid, []);
                if ($existing['deleted_at'] || $existing['archived_at'] || $existing['display_number'] === null) {
                    $next++;
                    $pdo->prepare('UPDATE habits SET display_number = :n WHERE id = :id')
                        ->execute(['n' => $next, 'id' => $hid]);
                }
            } else {
                $next++;
                $pdo->prepare(
                    'INSERT INTO habits (
                        user_id, habit_key, display_number, name, description,
                        frequency_type, tracking_mode, target_per_period, unit, is_system, active
                     ) VALUES (
                        :uid, :k, :n, :name, :description,
                        \'daily\', :mode, :target, :unit, 1, 1
                     )'
                )->execute([
                    'uid' => $userId,
                    'k' => $spec['habit_key'],
                    'n' => $next,
                    'name' => $spec['name'],
                    'description' => $spec['description'],
                    'mode' => $tracking,
                    'target' => $target,
                    'unit' => $unit,
                ]);
            }
        }
        $this->renumberActive($userId);
    }

    public function isSystem(array $habit): bool
    {
        return (int) ($habit['is_system'] ?? 0) === 1
            || in_array((string) ($habit['habit_key'] ?? ''), array_column(self::systemCatalog(), 'habit_key'), true);
    }

    /** @return list<array<string, mixed>> */
    public function listActive(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT h.*, la.name AS area_name, la.color AS area_color
             FROM habits h
             LEFT JOIN life_areas la ON la.id = h.area_id AND la.user_id = h.user_id
             WHERE h.user_id = :user_id
               AND h.active = 1
               AND h.deleted_at IS NULL
               AND h.archived_at IS NULL
             ORDER BY h.display_number ASC, h.id ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();
        return array_map([$this, 'hydrateHabit'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listAll(int $userId, bool $includeArchived = false): array
    {
        $sql = 'SELECT h.*, la.name AS area_name, la.color AS area_color
                FROM habits h
                LEFT JOIN life_areas la ON la.id = h.area_id AND la.user_id = h.user_id
                WHERE h.user_id = :user_id
                  AND h.deleted_at IS NULL';
        if (!$includeArchived) {
            $sql .= ' AND h.archived_at IS NULL';
        }
        $sql .= ' ORDER BY
                    CASE WHEN h.archived_at IS NULL THEN 0 ELSE 1 END,
                    h.display_number IS NULL,
                    h.display_number ASC,
                    h.id ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return array_map([$this, 'hydrateHabit'], $stmt->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT h.*, la.name AS area_name, la.color AS area_color
             FROM habits h
             LEFT JOIN life_areas la ON la.id = h.area_id AND la.user_id = h.user_id
             WHERE h.user_id = :user_id AND h.id = :id AND h.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $habit = $this->hydrateHabit($row);
        $habit['schedule_days'] = $this->scheduleDaysFor((int) $habit['id']);
        return $habit;
    }

    /** @param array<string, mixed> $data */
    public function create(int $userId, array $data): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $maxStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(display_number), 0)
                 FROM habits
                 WHERE user_id = :user_id
                   AND active = 1
                   AND deleted_at IS NULL
                   AND archived_at IS NULL'
            );
            $maxStmt->execute(['user_id' => $userId]);
            $nextNumber = (int) $maxStmt->fetchColumn() + 1;

            $stmt = $pdo->prepare(
                'INSERT INTO habits (
                    user_id, habit_key, display_number, name, description, area_id,
                    frequency_type, tracking_mode, target_per_period, current_value, unit, minimum_value, preferred_time,
                    start_date, end_date, active, linked_goal_id, notes
                 ) VALUES (
                    :user_id, :habit_key, :display_number, :name, :description, :area_id,
                    :frequency_type, :tracking_mode, :target_per_period, :current_value, :unit, :minimum_value, :preferred_time,
                    :start_date, :end_date, 1, :linked_goal_id, :notes
                 )'
            );
            $tracking = (string) ($data['tracking_mode'] ?? 'months');
            if (!in_array($tracking, self::VALID_TRACKING, true)) {
                $tracking = 'months';
            }
            $stmt->execute([
                'user_id' => $userId,
                'habit_key' => $data['habit_key'] ?? null,
                'display_number' => $nextNumber,
                'name' => (string) ($data['name'] ?? ''),
                'description' => $data['description'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'frequency_type' => $data['frequency_type'] ?? 'daily',
                'tracking_mode' => $tracking,
                'target_per_period' => (int) ($data['target_per_period'] ?? ($tracking === 'units' ? 1 : 1)),
                'current_value' => (float) ($data['current_value'] ?? 0),
                'unit' => (string) ($data['unit'] ?? ($tracking === 'units' ? 'unidad' : 'mes')),
                'minimum_value' => $data['minimum_value'] ?? null,
                'preferred_time' => $data['preferred_time'] ?? 'anytime',
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'linked_goal_id' => $data['linked_goal_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $habitId = (int) $pdo->lastInsertId();

            $frequency = (string) ($data['frequency_type'] ?? 'daily');
            $days = $data['days'] ?? $data['schedule_days'] ?? null;
            if ($frequency === 'weekdays' || $frequency === 'custom_days') {
                $this->replaceScheduleDays($habitId, is_array($days) ? $days : ($frequency === 'weekdays' ? [1, 2, 3, 4, 5] : []));
            }

            $this->renumberActive($userId);
            $pdo->commit();
            return $habitId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(int $userId, int $id, array $data): void
    {
        $existing = $this->find($userId, $id);
        if ($existing === null) {
            throw new RuntimeException('Habit not found');
        }
        if ($this->isSystem($existing)) {
            throw new RuntimeException('Los hábitos del sistema no se pueden editar.');
        }

        $pdo = Database::pdo();
        $fields = [
            'name' => $data['name'] ?? $existing['name'],
            'description' => array_key_exists('description', $data) ? $data['description'] : $existing['description'],
            'area_id' => array_key_exists('area_id', $data) ? $data['area_id'] : $existing['area_id'],
            'frequency_type' => $data['frequency_type'] ?? $existing['frequency_type'],
            'tracking_mode' => $data['tracking_mode'] ?? ($existing['tracking_mode'] ?? 'months'),
            'target_per_period' => (int) ($data['target_per_period'] ?? $existing['target_per_period']),
            'current_value' => array_key_exists('current_value', $data) ? $data['current_value'] : ($existing['current_value'] ?? 0),
            'unit' => $data['unit'] ?? $existing['unit'],
            'minimum_value' => array_key_exists('minimum_value', $data) ? $data['minimum_value'] : $existing['minimum_value'],
            'preferred_time' => $data['preferred_time'] ?? $existing['preferred_time'],
            'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : $existing['start_date'],
            'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $existing['end_date'],
            'linked_goal_id' => array_key_exists('linked_goal_id', $data) ? $data['linked_goal_id'] : $existing['linked_goal_id'],
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $existing['notes'],
            'habit_key' => array_key_exists('habit_key', $data) ? $data['habit_key'] : $existing['habit_key'],
        ];

        $stmt = $pdo->prepare(
            'UPDATE habits SET
                habit_key = :habit_key,
                name = :name,
                description = :description,
                area_id = :area_id,
                frequency_type = :frequency_type,
                tracking_mode = :tracking_mode,
                target_per_period = :target_per_period,
                current_value = :current_value,
                unit = :unit,
                minimum_value = :minimum_value,
                preferred_time = :preferred_time,
                start_date = :start_date,
                end_date = :end_date,
                linked_goal_id = :linked_goal_id,
                notes = :notes
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ...$fields,
            'id' => $id,
            'user_id' => $userId,
        ]);

        if (isset($data['days']) || isset($data['schedule_days']) || isset($data['frequency_type'])) {
            $frequency = (string) $fields['frequency_type'];
            $days = $data['days'] ?? $data['schedule_days'] ?? $existing['schedule_days'] ?? null;
            if ($frequency === 'weekdays' || $frequency === 'custom_days') {
                $this->replaceScheduleDays($id, is_array($days) ? $days : ($frequency === 'weekdays' ? [1, 2, 3, 4, 5] : []));
            } else {
                $this->replaceScheduleDays($id, []);
            }
        }
    }

    public function archive(int $userId, int $id): void
    {
        $existing = $this->find($userId, $id);
        if ($existing !== null && $this->isSystem($existing)) {
            throw new RuntimeException('Este hábito del sistema no se puede archivar.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE habits
                 SET active = 0, archived_at = UTC_TIMESTAMP(), display_number = NULL
                 WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL AND archived_at IS NULL'
            );
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Habit not found');
            }
            $this->renumberActive($userId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(int $userId, int $id): void
    {
        $existing = $this->find($userId, $id);
        if ($existing !== null && $this->isSystem($existing)) {
            throw new RuntimeException('Este hábito del sistema no se puede eliminar.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE habits
                 SET active = 0, deleted_at = UTC_TIMESTAMP(), display_number = NULL
                 WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
            );
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Habit not found');
            }
            $this->renumberActive($userId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param list<int> $orderedIds */
    public function reorder(int $userId, array $orderedIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $n = 0;
            $stmt = $pdo->prepare(
                'UPDATE habits
                 SET display_number = :display_number
                 WHERE id = :id
                   AND user_id = :user_id
                   AND active = 1
                   AND deleted_at IS NULL
                   AND archived_at IS NULL'
            );
            foreach ($orderedIds as $habitId) {
                $n++;
                $stmt->execute([
                    'display_number' => $n,
                    'id' => (int) $habitId,
                    'user_id' => $userId,
                ]);
            }
            $this->renumberActive($userId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function renumberActive(int $userId): void
    {
        $pdo = Database::pdo();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM habits
                 WHERE user_id = :user_id
                   AND active = 1
                   AND deleted_at IS NULL
                   AND archived_at IS NULL
                 ORDER BY display_number IS NULL, display_number ASC, id ASC'
            );
            $stmt->execute(['user_id' => $userId]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $upd = $pdo->prepare('UPDATE habits SET display_number = :n WHERE id = :id AND user_id = :user_id');
            $n = 0;
            foreach ($ids as $habitId) {
                $n++;
                $upd->execute(['n' => $n, 'id' => (int) $habitId, 'user_id' => $userId]);
            }
            if ($ownTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function log(
        int $userId,
        int $habitId,
        string $date,
        string $status,
        ?float $quantity = null,
        ?string $note = null
    ): void {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid habit log status');
        }

        $habit = $this->find($userId, $habitId);
        if ($habit === null) {
            throw new RuntimeException('Habit not found');
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO habit_logs (habit_id, log_date, status, quantity, note, logged_at)
             VALUES (:habit_id, :log_date, :status, :quantity, :note, CURTIME())
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                quantity = VALUES(quantity),
                note = VALUES(note),
                logged_at = VALUES(logged_at),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'habit_id' => $habitId,
            'log_date' => $date,
            'status' => $status,
            'quantity' => $quantity,
            'note' => $note,
        ]);

        if (($habit['tracking_mode'] ?? 'daily') === 'units' && $status === 'completed') {
            $inc = $quantity !== null && $quantity > 0 ? (float) $quantity : 1.0;
            $this->bumpUnits($userId, $habitId, $inc);
        }
    }

    /**
     * Suma o resta ml del día (agua). Completa al llegar a la meta.
     *
     * @return array{quantity:float,target:float,status:string,done:bool}
     */
    public function addDailyQuantity(int $userId, int $habitId, string $date, float $delta): array
    {
        $habit = $this->find($userId, $habitId);
        if ($habit === null) {
            throw new RuntimeException('Habit not found');
        }
        $target = max(1.0, (float) ($habit['target_per_period'] ?? 2000));
        $logs = $this->logsForDate($userId, $date);
        $current = (float) (($logs[$habitId]['quantity'] ?? 0) ?: 0);
        $next = max(0.0, $current + $delta);
        $done = $next + 0.0001 >= $target;
        $status = $done ? 'completed' : 'missed';
        $this->log($userId, $habitId, $date, $status, $next, $logs[$habitId]['note'] ?? null);
        return [
            'quantity' => $next,
            'target' => $target,
            'status' => $status,
            'done' => $done,
        ];
    }

    /**
     * Resumen del hábito de agua para Métricas.
     *
     * @return array{
     *   today_ml:float,target_ml:float,today_done:bool,
     *   week_days_done:int,week_days:int,month_days_done:int,month_days:int,
     *   year_days_done:int,year_days:int,avg_week_ml:float,streak:int
     * }
     */
    public function waterStats(int $userId, ?DateTimeImmutable $today = null): array
    {
        $s = $this->dailyQtyStats($userId, self::KEY_WATER, 2000.0, $today);
        return [
            'today_ml' => $s['today_qty'],
            'target_ml' => $s['target_qty'],
            'today_done' => $s['today_done'],
            'week_days_done' => $s['week_days_done'],
            'week_days' => $s['week_days'],
            'month_days_done' => $s['month_days_done'],
            'month_days' => $s['month_days'],
            'year_days_done' => $s['year_days_done'],
            'year_days' => $s['year_days'],
            'avg_week_ml' => $s['avg_week_qty'],
            'streak' => $s['streak'],
        ];
    }

    /**
     * Resumen del hábito de frutas para Métricas.
     *
     * @return array{
     *   today:float,target:float,today_done:bool,
     *   week_days_done:int,week_days:int,month_days_done:int,month_days:int,
     *   year_days_done:int,year_days:int,avg_week:float,streak:int
     * }
     */
    public function fruitStats(int $userId, ?DateTimeImmutable $today = null): array
    {
        $s = $this->dailyQtyStats($userId, self::KEY_FRUIT, 3.0, $today);
        return [
            'today' => $s['today_qty'],
            'target' => $s['target_qty'],
            'today_done' => $s['today_done'],
            'week_days_done' => $s['week_days_done'],
            'week_days' => $s['week_days'],
            'month_days_done' => $s['month_days_done'],
            'month_days' => $s['month_days'],
            'year_days_done' => $s['year_days_done'],
            'year_days' => $s['year_days'],
            'avg_week' => $s['avg_week_qty'],
            'streak' => $s['streak'],
        ];
    }

    /**
     * @return array{
     *   today_qty:float,target_qty:float,today_done:bool,
     *   week_days_done:int,week_days:int,month_days_done:int,month_days:int,
     *   year_days_done:int,year_days:int,avg_week_qty:float,streak:int
     * }
     */
    private function dailyQtyStats(int $userId, string $habitKey, float $defaultTarget, ?DateTimeImmutable $today = null): array
    {
        $today = $today ?? now_local();
        $this->ensureSystemHabits($userId);
        $habit = null;
        foreach ($this->listActive($userId) as $row) {
            if (($row['habit_key'] ?? '') === $habitKey) {
                $habit = $row;
                break;
            }
        }
        $target = max(1.0, (float) ($habit['target_per_period'] ?? $defaultTarget));
        $daysInMonth = (int) $today->format('t');
        $empty = [
            'today_qty' => 0.0,
            'target_qty' => $target,
            'today_done' => false,
            'week_days_done' => 0,
            'week_days' => 7,
            'month_days_done' => 0,
            'month_days' => $daysInMonth,
            'year_days_done' => 0,
            'year_days' => ((int) $today->format('z')) + 1,
            'avg_week_qty' => 0.0,
            'streak' => 0,
        ];
        if ($habit === null) {
            return $empty;
        }

        $habitId = (int) $habit['id'];
        $todayYmd = $today->format('Y-m-d');
        $weekStart = $today->modify('monday this week')->format('Y-m-d');
        $monthStart = $today->format('Y-m-01');
        $monthEnd = $today->format('Y-m-t');
        $yearStart = $today->format('Y-01-01');

        $stmt = Database::pdo()->prepare(
            'SELECT log_date, quantity, status
             FROM habit_logs
             WHERE habit_id = :hid AND log_date BETWEEN :from AND :to
             ORDER BY log_date DESC'
        );
        $stmt->execute(['hid' => $habitId, 'from' => $yearStart, 'to' => $todayYmd]);
        $byDate = [];
        foreach ($stmt->fetchAll() as $row) {
            $byDate[(string) $row['log_date']] = $row;
        }

        $metGoal = static function (array $byDate, string $d, float $target): bool {
            $qty = (float) (($byDate[$d]['quantity'] ?? 0) ?: 0);
            return $qty + 0.0001 >= $target;
        };

        $todayQty = (float) (($byDate[$todayYmd]['quantity'] ?? 0) ?: 0);
        $weekDone = 0;
        $weekSum = 0.0;
        $weekElapsed = 0;
        $cursor = new DateTimeImmutable($weekStart);
        for ($i = 0; $i < 7; $i++) {
            $d = $cursor->modify("+{$i} day")->format('Y-m-d');
            if ($d > $todayYmd) {
                break;
            }
            $weekElapsed++;
            $qty = (float) (($byDate[$d]['quantity'] ?? 0) ?: 0);
            $weekSum += $qty;
            if ($metGoal($byDate, $d, $target)) {
                $weekDone++;
            }
        }

        $monthDone = 0;
        $mCursor = new DateTimeImmutable($monthStart);
        $mEnd = new DateTimeImmutable($monthEnd);
        while ($mCursor <= $mEnd) {
            $d = $mCursor->format('Y-m-d');
            if ($d <= $todayYmd && $metGoal($byDate, $d, $target)) {
                $monthDone++;
            }
            $mCursor = $mCursor->modify('+1 day');
        }

        $yearDone = 0;
        $yearDays = ((int) $today->format('z')) + 1;
        foreach ($byDate as $d => $_row) {
            if ($metGoal($byDate, (string) $d, $target)) {
                $yearDone++;
            }
        }

        $streak = 0;
        $probe = $today;
        while (true) {
            $d = $probe->format('Y-m-d');
            if ($d < $yearStart) {
                break;
            }
            if (!$metGoal($byDate, $d, $target)) {
                if ($d === $todayYmd) {
                    $probe = $probe->modify('-1 day');
                    continue;
                }
                break;
            }
            $streak++;
            $probe = $probe->modify('-1 day');
        }

        return [
            'today_qty' => $todayQty,
            'target_qty' => $target,
            'today_done' => $metGoal($byDate, $todayYmd, $target),
            'week_days_done' => $weekDone,
            'week_days' => 7,
            'month_days_done' => $monthDone,
            'month_days' => $daysInMonth,
            'year_days_done' => $yearDone,
            'year_days' => $yearDays,
            'avg_week_qty' => $weekElapsed > 0 ? round($weekSum / $weekElapsed, 1) : 0.0,
            'streak' => $streak,
        ];
    }

    /** @return array{months: array<int,bool>, checked: int, percent: float, year: int} */
    public function monthProgress(int $userId, int $habitId, ?int $year = null): array
    {
        $habit = $this->find($userId, $habitId);
        if ($habit === null) {
            throw new RuntimeException('Habit not found');
        }
        $year = $year ?? (int) date('Y');
        $map = MonthProgress::emptyMap();
        $stmt = Database::pdo()->prepare(
            'SELECT month_num FROM habit_month_checks
             WHERE habit_id = :hid AND year_num = :year AND is_checked = 1'
        );
        $stmt->execute(['hid' => $habitId, 'year' => $year]);
        foreach ($stmt->fetchAll() as $row) {
            $m = (int) $row['month_num'];
            if ($m >= 1 && $m <= 12) {
                $map[$m] = true;
            }
        }
        $summary = MonthProgress::summary($map);
        $summary['year'] = $year;
        return $summary;
    }

    public function toggleMonth(int $userId, int $habitId, int $year, int $month): array
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Mes inválido');
        }
        $habit = $this->find($userId, $habitId);
        if ($habit === null) {
            throw new RuntimeException('Habit not found');
        }
        if (($habit['tracking_mode'] ?? '') !== 'months') {
            throw new RuntimeException('Este hábito no se mide por meses');
        }

        $pdo = Database::pdo();
        $find = $pdo->prepare(
            'SELECT id, is_checked FROM habit_month_checks
             WHERE habit_id = :hid AND year_num = :year AND month_num = :m LIMIT 1'
        );
        $find->execute(['hid' => $habitId, 'year' => $year, 'm' => $month]);
        $row = $find->fetch();

        if ($row) {
            $new = (int) $row['is_checked'] ? 0 : 1;
            $pdo->prepare(
                'UPDATE habit_month_checks SET is_checked = :c, updated_at = UTC_TIMESTAMP() WHERE id = :id'
            )->execute(['c' => $new, 'id' => (int) $row['id']]);
        } else {
            $pdo->prepare(
                'INSERT INTO habit_month_checks (habit_id, year_num, month_num, is_checked)
                 VALUES (:hid, :year, :m, 1)'
            )->execute(['hid' => $habitId, 'year' => $year, 'm' => $month]);
        }

        $summary = $this->monthProgress($userId, $habitId, $year);
        $pdo->prepare(
            'UPDATE habits SET progress_percent = :percent, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id'
        )->execute([
            'percent' => $summary['percent'],
            'id' => $habitId,
            'user_id' => $userId,
        ]);

        return $summary;
    }

    public function bumpUnits(int $userId, int $habitId, float $delta = 1.0): array
    {
        $habit = $this->find($userId, $habitId);
        if ($habit === null) {
            throw new RuntimeException('Habit not found');
        }
        if (($habit['tracking_mode'] ?? '') !== 'units') {
            throw new RuntimeException('Este hábito no se mide por unidades');
        }

        $target = max(0.0, (float) ($habit['target_per_period'] ?? 0));
        $current = max(0.0, (float) ($habit['current_value'] ?? 0) + $delta);
        $percent = $target > 0 ? round(min(100, ($current / $target) * 100), 2) : 0.0;

        Database::pdo()->prepare(
            'UPDATE habits
             SET current_value = :current_value, progress_percent = :percent, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id'
        )->execute([
            'current_value' => $current,
            'percent' => $percent,
            'id' => $habitId,
            'user_id' => $userId,
        ]);

        return [
            'current_value' => $current,
            'target' => $target,
            'percent' => $percent,
            'unit' => $habit['unit'] ?? '',
        ];
    }

    public function setUnits(int $userId, int $habitId, float $current, ?float $target = null): array
    {
        $habit = $this->find($userId, $habitId);
        if ($habit === null) {
            throw new RuntimeException('Habit not found');
        }
        if (($habit['tracking_mode'] ?? '') !== 'units') {
            throw new RuntimeException('Este hábito no se mide por unidades');
        }

        $target = $target !== null ? max(1.0, $target) : max(0.0, (float) ($habit['target_per_period'] ?? 0));
        $current = max(0.0, $current);
        $percent = $target > 0 ? round(min(100, ($current / $target) * 100), 2) : 0.0;

        Database::pdo()->prepare(
            'UPDATE habits
             SET current_value = :current_value,
                 target_per_period = :target_per_period,
                 progress_percent = :percent,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id'
        )->execute([
            'current_value' => $current,
            'target_per_period' => $target,
            'percent' => $percent,
            'id' => $habitId,
            'user_id' => $userId,
        ]);

        return [
            'current_value' => $current,
            'target' => $target,
            'percent' => $percent,
            'unit' => $habit['unit'] ?? '',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function logsForDate(int $userId, string $date): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT hl.*
             FROM habit_logs hl
             INNER JOIN habits h ON h.id = hl.habit_id
             WHERE h.user_id = :user_id
               AND h.deleted_at IS NULL
               AND hl.log_date = :log_date'
        );
        $stmt->execute(['user_id' => $userId, 'log_date' => $date]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['habit_id']] = $row;
        }
        return $map;
    }

    /** @return list<array<string, mixed>> */
    public function todayHabits(int $userId, ?string $date = null): array
    {
        $dt = $date !== null
            ? new DateTimeImmutable($date)
            : now_local();
        $dateStr = $dt->format('Y-m-d');
        $habits = $this->listActive($userId);
        $logs = $this->logsForDate($userId, $dateStr);
        $scheduleCache = $this->scheduleDaysMap(array_column($habits, 'id'));

        $result = [];
        foreach ($habits as $habit) {
            $habit['schedule_days'] = $scheduleCache[(int) $habit['id']] ?? [];
            if (!$this->isScheduledOn($habit, $dt)) {
                continue;
            }
            $log = $logs[(int) $habit['id']] ?? null;
            $habit['log'] = $log;
            $habit['log_status'] = $log['status'] ?? null;
            $habit['log_quantity'] = isset($log['quantity']) ? (float) $log['quantity'] : 0.0;
            $result[] = $habit;
        }
        return $result;
    }

    /** @return array{current_streak:int, best_streak:int} */
    public function streak(int $habitId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT log_date, status
             FROM habit_logs
             WHERE habit_id = :habit_id
             ORDER BY log_date ASC'
        );
        $stmt->execute(['habit_id' => $habitId]);
        $rows = $stmt->fetchAll();

        $best = 0;
        $run = 0;
        $prevDate = null;
        $completedDates = [];

        foreach ($rows as $row) {
            if ($row['status'] !== 'completed' && $row['status'] !== 'partial') {
                $run = 0;
                $prevDate = null;
                continue;
            }
            $date = $row['log_date'];
            $completedDates[$date] = true;
            if ($prevDate !== null) {
                $expected = (new DateTimeImmutable($prevDate))->modify('+1 day')->format('Y-m-d');
                if ($date !== $expected) {
                    $run = 0;
                }
            }
            $run++;
            $best = max($best, $run);
            $prevDate = $date;
        }

        $current = 0;
        $cursor = now_local();
        // If today is not yet logged, allow streak to start from yesterday.
        $today = $cursor->format('Y-m-d');
        if (!isset($completedDates[$today])) {
            $cursor = $cursor->modify('-1 day');
        }
        while (isset($completedDates[$cursor->format('Y-m-d')])) {
            $current++;
            $cursor = $cursor->modify('-1 day');
        }

        return [
            'current_streak' => $current,
            'best_streak' => max($best, $current),
        ];
    }

    /** @param array<string, mixed> $habit */
    public function isScheduledOn(array $habit, DateTimeInterface $date): bool
    {
        if ($this->isSystem($habit)) {
            return true;
        }
        $ymd = $date->format('Y-m-d');
        if (!empty($habit['start_date']) && $ymd < $habit['start_date']) {
            return false;
        }
        if (!empty($habit['end_date']) && $ymd > $habit['end_date']) {
            return false;
        }

        $weekday = (int) $date->format('N'); // 1=Mon .. 7=Sun
        $frequency = (string) ($habit['frequency_type'] ?? 'daily');
        $days = $habit['schedule_days'] ?? null;
        if ($days === null && isset($habit['id'])) {
            $days = $this->scheduleDaysFor((int) $habit['id']);
        }
        if (!is_array($days)) {
            $days = [];
        }
        $days = array_map('intval', $days);

        return match ($frequency) {
            'daily' => true,
            'weekdays' => $days !== [] ? in_array($weekday, $days, true) : ($weekday >= 1 && $weekday <= 5),
            'custom_days' => in_array($weekday, $days, true),
            'weekly', 'monthly', 'interval' => true,
            default => true,
        };
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function hydrateHabit(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['display_number'] = $row['display_number'] !== null ? (int) $row['display_number'] : null;
        $row['active'] = (int) $row['active'];
        $row['color_group'] = habit_color_group((int) ($row['display_number'] ?? 0));
        $row['tracking_mode'] = (string) ($row['tracking_mode'] ?? 'daily');
        $row['current_value'] = isset($row['current_value']) ? (float) $row['current_value'] : 0.0;
        $row['progress_percent'] = isset($row['progress_percent']) ? (float) $row['progress_percent'] : 0.0;
        $row['target_per_period'] = isset($row['target_per_period']) ? (float) $row['target_per_period'] : 1.0;
        $row['is_system'] = (int) ($row['is_system'] ?? 0);
        return $row;
    }

    /** @return list<int> */
    private function scheduleDaysFor(int $habitId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT weekday FROM habit_schedule_days WHERE habit_id = :habit_id ORDER BY weekday ASC'
        );
        $stmt->execute(['habit_id' => $habitId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param list<int|string> $habitIds
     * @return array<int, list<int>>
     */
    private function scheduleDaysMap(array $habitIds): array
    {
        $habitIds = array_values(array_unique(array_map('intval', $habitIds)));
        if ($habitIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($habitIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT habit_id, weekday FROM habit_schedule_days WHERE habit_id IN ($placeholders) ORDER BY weekday ASC"
        );
        $stmt->execute($habitIds);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['habit_id']][] = (int) $row['weekday'];
        }
        return $map;
    }

    /** @param list<int|string> $days */
    private function replaceScheduleDays(int $habitId, array $days): void
    {
        $pdo = Database::pdo();
        $del = $pdo->prepare('DELETE FROM habit_schedule_days WHERE habit_id = :habit_id');
        $del->execute(['habit_id' => $habitId]);

        $ins = $pdo->prepare(
            'INSERT INTO habit_schedule_days (habit_id, weekday) VALUES (:habit_id, :weekday)'
        );
        $seen = [];
        foreach ($days as $day) {
            $weekday = (int) $day;
            if ($weekday < 1 || $weekday > 7 || isset($seen[$weekday])) {
                continue;
            }
            $seen[$weekday] = true;
            $ins->execute(['habit_id' => $habitId, 'weekday' => $weekday]);
        }
    }
}
