<?php

declare(strict_types=1);

final class HabitService
{
    private const VALID_STATUSES = ['completed', 'partial', 'skipped_justified', 'missed'];

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
            if (!in_array($tracking, ['months', 'units', 'daily'], true)) {
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

    public function setUnits(int $userId, int $habitId, float $current): array
    {
        $habit = $this->find($userId, $habitId);
        if ($habit === null) {
            throw new RuntimeException('Habit not found');
        }
        if (($habit['tracking_mode'] ?? '') !== 'units') {
            throw new RuntimeException('Este hábito no se mide por unidades');
        }

        $target = max(0.0, (float) ($habit['target_per_period'] ?? 0));
        $current = max(0.0, $current);
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
