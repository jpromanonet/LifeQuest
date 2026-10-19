<?php

declare(strict_types=1);

final class SportService
{
    public const REPS_GOAL = 100;

    private const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private const CHART_COLORS = ['#7C83E1', '#78C6B0', '#F4B8A8', '#F6D58A', '#C4B5FD', '#F9A8D4', '#93C5FD', '#FDBA74', '#86EFAC', '#FDA4AF'];

    /** @return list<int> */
    public static function repsOptions(): array
    {
        $options = [];
        for ($n = 10; $n <= self::REPS_GOAL; $n += 10) {
            $options[] = $n;
        }
        return $options;
    }

    public const DAY_SHORT = [
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
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sport_profiles (
              user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
              height_cm DECIMAL(5,1) NULL,
              has_routine TINYINT(1) NOT NULL DEFAULT 0,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT fk_sport_profiles_user FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sport_weights (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id BIGINT UNSIGNED NOT NULL,
              weighed_on DATE NOT NULL,
              weight_kg DECIMAL(5,2) NOT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_sport_weight_day (user_id, weighed_on),
              KEY idx_sport_weight_user (user_id, weighed_on),
              CONSTRAINT fk_sport_weights_user FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sport_disciplines (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id BIGINT UNSIGNED NOT NULL,
              name VARCHAR(120) NOT NULL,
              kind ENUM('distance','class') NOT NULL,
              unit VARCHAR(16) NOT NULL DEFAULT 'km',
              target_amount DECIMAL(8,2) NULL,
              weekdays VARCHAR(20) NOT NULL DEFAULT '',
              sort_order INT UNSIGNED NOT NULL DEFAULT 0,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              deleted_at DATETIME NULL,
              KEY idx_sport_disc_user (user_id, deleted_at, sort_order),
              CONSTRAINT fk_sport_disciplines_user FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sport_logs (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id BIGINT UNSIGNED NOT NULL,
              discipline_id BIGINT UNSIGNED NOT NULL,
              log_date DATE NOT NULL,
              quantity DECIMAL(8,2) NULL,
              attended TINYINT(1) NOT NULL DEFAULT 0,
              UNIQUE KEY uq_sport_log (discipline_id, log_date),
              KEY idx_sport_log_user_date (user_id, log_date),
              CONSTRAINT fk_sport_logs_user FOREIGN KEY (user_id) REFERENCES users(id),
              CONSTRAINT fk_sport_logs_disc FOREIGN KEY (discipline_id) REFERENCES sport_disciplines(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sport_exercises (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id BIGINT UNSIGNED NOT NULL,
              name VARCHAR(120) NOT NULL,
              reps SMALLINT UNSIGNED NOT NULL DEFAULT 10,
              sort_order INT UNSIGNED NOT NULL DEFAULT 0,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              deleted_at DATETIME NULL,
              KEY idx_sport_ex_user (user_id, deleted_at, sort_order),
              CONSTRAINT fk_sport_exercises_user FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sport_exercise_logs (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id BIGINT UNSIGNED NOT NULL,
              exercise_id BIGINT UNSIGNED NOT NULL,
              log_date DATE NOT NULL,
              is_done TINYINT(1) NOT NULL DEFAULT 1,
              reps SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              UNIQUE KEY uq_sport_ex_log (exercise_id, log_date),
              CONSTRAINT fk_sport_ex_logs_user FOREIGN KEY (user_id) REFERENCES users(id),
              CONSTRAINT fk_sport_ex_logs_ex FOREIGN KEY (exercise_id) REFERENCES sport_exercises(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (!$pdo->query("SHOW COLUMNS FROM sport_exercises LIKE 'reps'")->fetch()) {
            $pdo->exec('ALTER TABLE sport_exercises ADD COLUMN reps SMALLINT UNSIGNED NOT NULL DEFAULT 10 AFTER name');
        }
        if (!$pdo->query("SHOW COLUMNS FROM sport_exercise_logs LIKE 'reps'")->fetch()) {
            $pdo->exec('ALTER TABLE sport_exercise_logs ADD COLUMN reps SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_done');
        }
        $pdo->exec(
            'UPDATE sport_exercise_logs l
             INNER JOIN sport_exercises e ON e.id = l.exercise_id
             SET l.reps = e.reps
             WHERE l.is_done = 1 AND l.reps = 0 AND e.reps > 0'
        );
        $done = true;
    }

    /** @return array<string, mixed> */
    public function board(int $userId, string $date): array
    {
        $this->ensureTable();
        $day = $this->assertDate($date);
        $profile = $this->profile($userId);
        $weights = $this->weights($userId);
        $latest = $weights[0] ?? null;
        $previous = $weights[1] ?? null;
        $height = $profile['height_cm'];
        $weight = $latest ? (float) $latest['weight_kg'] : null;
        $bmi = self::bmi($weight, $height);
        $disciplines = $this->disciplinesForDate($userId, $day);
        $exercises = $this->exercisesForDate($userId, $day);
        $week = $this->weekBounds($day);
        $metrics = $this->weekMetrics($userId, $week['start'], $week['end'], $disciplines, $exercises, $profile['has_routine']);
        $byDay = $this->seriesByDay($userId, $week['start'], $week['end']);
        $seriesWeek = 0;
        foreach ($week['days'] as $index => $dayRow) {
            $amount = (int) ($byDay[$dayRow['date']] ?? 0);
            $week['days'][$index]['series'] = $amount;
            $seriesWeek += $amount;
        }
        $metrics['series_week'] = $seriesWeek;

        return [
            'date' => $day,
            'weekday' => (int) (new DateTimeImmutable($day))->format('N'),
            'week' => $week,
            'profile' => $profile,
            'weights' => array_slice($weights, 0, 8),
            'latest_weight' => $weight,
            'weight_delta' => ($weight !== null && $previous) ? round($weight - (float) $previous['weight_kg'], 2) : null,
            'bmi' => $bmi,
            'bmi_label' => self::bmiLabel($bmi),
            'disciplines' => $disciplines,
            'exercises' => $exercises,
            'metrics' => $metrics,
        ];
    }

    public function saveBody(int $userId, mixed $heightRaw, mixed $weightRaw, string $date): void
    {
        $this->ensureTable();
        $day = $this->assertDate($date);
        $height = $this->optionalDecimal($heightRaw, 80, 250, 'La altura va en centímetros, entre 80 y 250.');
        $weight = $this->optionalDecimal($weightRaw, 25, 350, 'El peso va en kilos, entre 25 y 350.');
        if ($height === null && $weight === null) {
            throw new InvalidArgumentException('Cargá la altura, el peso, o los dos.');
        }
        if ($height !== null) {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO sport_profiles (user_id, height_cm, has_routine)
                 VALUES (:uid, :height, 0)
                 ON DUPLICATE KEY UPDATE height_cm = VALUES(height_cm)'
            );
            $stmt->execute(['uid' => $userId, 'height' => $height]);
        }
        if ($weight !== null) {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO sport_weights (user_id, weighed_on, weight_kg)
                 VALUES (:uid, :day, :weight)
                 ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg)'
            );
            $stmt->execute(['uid' => $userId, 'day' => $day, 'weight' => $weight]);
        }
    }

    public function setRoutine(int $userId, bool $enabled): void
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sport_profiles (user_id, has_routine)
             VALUES (:uid, :flag)
             ON DUPLICATE KEY UPDATE has_routine = VALUES(has_routine)'
        );
        $stmt->execute(['uid' => $userId, 'flag' => $enabled ? 1 : 0]);
    }

    public function addExercise(int $userId, string $name, mixed $reps): void
    {
        $this->ensureTable();
        $name = $this->cleanName($name, 'Poné el nombre del ejercicio.');
        $amount = $this->normalizeReps($reps);
        $read = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort
             FROM sport_exercises WHERE user_id = :uid AND deleted_at IS NULL'
        );
        $read->execute(['uid' => $userId]);
        $sort = (int) ($read->fetch()['next_sort'] ?? 1);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sport_exercises (user_id, name, reps, sort_order) VALUES (:uid, :name, :reps, :sort)'
        );
        $stmt->execute(['uid' => $userId, 'name' => $name, 'reps' => $amount, 'sort' => $sort]);
    }

    public function updateExerciseReps(int $userId, int $id, mixed $reps): void
    {
        $this->ensureTable();
        if ($this->findExercise($userId, $id) === null) {
            throw new InvalidArgumentException('No encontré ese ejercicio.');
        }
        $amount = $this->normalizeReps($reps);
        $stmt = Database::pdo()->prepare(
            'UPDATE sport_exercises SET reps = :reps
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['reps' => $amount, 'id' => $id, 'uid' => $userId]);
    }

    public function renameExercise(int $userId, int $id, string $name): void
    {
        $this->ensureTable();
        if ($this->findExercise($userId, $id) === null) {
            throw new InvalidArgumentException('No encontré ese ejercicio.');
        }
        $name = $this->cleanName($name, 'Poné el nombre del ejercicio.');
        $stmt = Database::pdo()->prepare(
            'UPDATE sport_exercises SET name = :name
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['name' => $name, 'id' => $id, 'uid' => $userId]);
    }

    /** @param list<int> $orderedIds */
    public function reorderExercises(int $userId, array $orderedIds): void
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'UPDATE sport_exercises SET sort_order = :sort
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $sort = 0;
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $sort++;
            $stmt->execute(['sort' => $sort, 'id' => $id, 'uid' => $userId]);
        }
    }

    public function toggleExercise(int $userId, int $id, string $date): void
    {
        $this->ensureTable();
        $day = $this->assertDate($date);
        $pdo = Database::pdo();
        $found = $pdo->prepare(
            'SELECT reps FROM sport_exercises
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $found->execute(['id' => $id, 'uid' => $userId]);
        $exercise = $found->fetch();
        if (!$exercise) {
            throw new InvalidArgumentException('No encontré ese ejercicio.');
        }
        $reps = (int) $exercise['reps'];
        $current = $pdo->prepare(
            'SELECT is_done FROM sport_exercise_logs WHERE exercise_id = :id AND log_date = :day LIMIT 1'
        );
        $current->execute(['id' => $id, 'day' => $day]);
        $row = $current->fetch();
        if ($row && (int) $row['is_done'] === 1) {
            $pdo->prepare('DELETE FROM sport_exercise_logs WHERE exercise_id = :id AND log_date = :day')
                ->execute(['id' => $id, 'day' => $day]);
            return;
        }
        $pdo->prepare(
            'INSERT INTO sport_exercise_logs (user_id, exercise_id, log_date, is_done, reps)
             VALUES (:uid, :id, :day, 1, :reps)
             ON DUPLICATE KEY UPDATE is_done = 1, reps = VALUES(reps)'
        )->execute(['uid' => $userId, 'id' => $id, 'day' => $day, 'reps' => $reps]);
    }

    public function deleteExercise(int $userId, int $id): void
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'UPDATE sport_exercises SET deleted_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        if ($stmt->rowCount() < 1) {
            throw new InvalidArgumentException('No encontré ese ejercicio.');
        }
    }

    /** @param array<string, mixed> $data */
    public function createDiscipline(int $userId, array $data): void
    {
        $this->ensureTable();
        $name = $this->cleanName((string) ($data['name'] ?? ''), 'Poné el nombre de la disciplina.');
        $kind = (string) ($data['kind'] ?? '');
        if (!in_array($kind, ['distance', 'class'], true)) {
            throw new InvalidArgumentException('Elegí si es por kilómetros o una clase.');
        }
        $days = self::normalizeWeekdays($data['weekdays'] ?? []);
        if ($days === []) {
            throw new InvalidArgumentException('Elegí al menos un día de la semana.');
        }
        $target = null;
        $unit = 'km';
        if ($kind === 'distance') {
            $target = $this->optionalDecimal($data['target_amount'] ?? null, 0.1, 200, 'Los kilómetros por día tienen que ser mayores a 0.');
            if ($target === null) {
                throw new InvalidArgumentException('Poné cuántos kilómetros por día.');
            }
            $unit = 'km';
        }
        $read = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort
             FROM sport_disciplines WHERE user_id = :uid AND deleted_at IS NULL'
        );
        $read->execute(['uid' => $userId]);
        $sort = (int) ($read->fetch()['next_sort'] ?? 1);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sport_disciplines (user_id, name, kind, unit, target_amount, weekdays, sort_order)
             VALUES (:uid, :name, :kind, :unit, :target, :days, :sort)'
        );
        $stmt->execute([
            'uid' => $userId,
            'name' => $name,
            'kind' => $kind,
            'unit' => $unit,
            'target' => $target,
            'days' => implode(',', $days),
            'sort' => $sort,
        ]);
    }

    public function deleteDiscipline(int $userId, int $id): void
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'UPDATE sport_disciplines SET deleted_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        if ($stmt->rowCount() < 1) {
            throw new InvalidArgumentException('No encontré esa disciplina.');
        }
    }

    public function logDiscipline(int $userId, int $id, string $date, mixed $quantity, mixed $attended): void
    {
        $this->ensureTable();
        $day = $this->assertDate($date);
        $disc = $this->findDiscipline($userId, $id);
        if ($disc === null) {
            throw new InvalidArgumentException('No encontré esa disciplina.');
        }
        if ($disc['kind'] === 'distance') {
            $km = $this->optionalDecimal($quantity, 0, 300, 'Los kilómetros no pueden ser negativos.');
            if ($km === null) {
                throw new InvalidArgumentException('Poné los kilómetros del día.');
            }
            Database::pdo()->prepare(
                'INSERT INTO sport_logs (user_id, discipline_id, log_date, quantity, attended)
                 VALUES (:uid, :id, :day, :qty, 0)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
            )->execute(['uid' => $userId, 'id' => $id, 'day' => $day, 'qty' => $km]);
            return;
        }
        $flag = (int) $attended === 1 ? 1 : 0;
        Database::pdo()->prepare(
            'INSERT INTO sport_logs (user_id, discipline_id, log_date, quantity, attended)
             VALUES (:uid, :id, :day, NULL, :flag)
             ON DUPLICATE KEY UPDATE attended = VALUES(attended)'
        )->execute(['uid' => $userId, 'id' => $id, 'day' => $day, 'flag' => $flag]);
    }

    public static function bmi(?float $weightKg, ?float $heightCm): ?float
    {
        if ($weightKg === null || $heightCm === null || $heightCm <= 0) {
            return null;
        }
        $meters = $heightCm / 100;
        return round($weightKg / ($meters * $meters), 1);
    }

    public static function bmiLabel(?float $bmi): string
    {
        if ($bmi === null) {
            return 'Falta altura o peso';
        }
        if ($bmi < 18.5) {
            return 'Bajo';
        }
        if ($bmi < 25) {
            return 'Normal';
        }
        if ($bmi < 30) {
            return 'Sobrepeso';
        }
        return 'Obesidad';
    }

    /** @return array{height_cm:?float,has_routine:bool} */
    private function profile(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT height_cm, has_routine FROM sport_profiles WHERE user_id = :uid LIMIT 1'
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return [
            'height_cm' => $row && $row['height_cm'] !== null ? (float) $row['height_cm'] : null,
            'has_routine' => $row ? (int) $row['has_routine'] === 1 : false,
        ];
    }

    /** @return list<array{weighed_on:string,weight_kg:float}> */
    private function weights(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT weighed_on, weight_kg FROM sport_weights
             WHERE user_id = :uid ORDER BY weighed_on DESC, id DESC LIMIT 12'
        );
        $stmt->execute(['uid' => $userId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'weighed_on' => (string) $row['weighed_on'],
                'weight_kg' => (float) $row['weight_kg'],
            ];
        }
        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function disciplinesForDate(int $userId, string $date): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT d.id, d.name, d.kind, d.unit, d.target_amount, d.weekdays,
                    l.quantity, l.attended
             FROM sport_disciplines d
             LEFT JOIN sport_logs l ON l.discipline_id = d.id AND l.log_date = :day
             WHERE d.user_id = :uid AND d.deleted_at IS NULL
             ORDER BY d.sort_order ASC, d.id ASC'
        );
        $stmt->execute(['uid' => $userId, 'day' => $date]);
        $weekday = (int) (new DateTimeImmutable($date))->format('N');
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $days = array_map('intval', array_filter(explode(',', (string) $row['weekdays'])));
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'kind' => (string) $row['kind'],
                'unit' => (string) $row['unit'],
                'target_amount' => $row['target_amount'] !== null ? (float) $row['target_amount'] : null,
                'weekdays' => $days,
                'planned_today' => in_array($weekday, $days, true),
                'quantity' => $row['quantity'] !== null ? (float) $row['quantity'] : null,
                'attended' => (int) ($row['attended'] ?? 0) === 1,
            ];
        }
        return $rows;
    }

    /** @return list<array{id:int,name:string,done:bool}> */
    private function exercisesForDate(int $userId, string $date): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.id, e.name, e.reps, COALESCE(l.is_done, 0) AS is_done
             FROM sport_exercises e
             LEFT JOIN sport_exercise_logs l ON l.exercise_id = e.id AND l.log_date = :day
             WHERE e.user_id = :uid AND e.deleted_at IS NULL
             ORDER BY e.sort_order ASC, e.id ASC'
        );
        $stmt->execute(['uid' => $userId, 'day' => $date]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'reps' => (int) ($row['reps'] ?? 10),
                'done' => (int) $row['is_done'] === 1,
            ];
        }
        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $disciplines
     * @param list<array{id:int,name:string,done:bool}> $exercises
     * @return array<string, mixed>
     */
    private function weekMetrics(int $userId, string $start, string $end, array $disciplines, array $exercises, bool $hasRoutine): array
    {
        $km = 0.0;
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(l.quantity), 0) AS km
             FROM sport_logs l
             INNER JOIN sport_disciplines d ON d.id = l.discipline_id
             WHERE l.user_id = :uid AND d.kind = \'distance\' AND d.deleted_at IS NULL
               AND l.log_date BETWEEN :start AND :end'
        );
        $stmt->execute(['uid' => $userId, 'start' => $start, 'end' => $end]);
        $km = (float) ($stmt->fetch()['km'] ?? 0);

        $scheduled = 0;
        $startDt = new DateTimeImmutable($start);
        for ($i = 0; $i < 7; $i++) {
            $dow = (int) $startDt->modify('+' . $i . ' days')->format('N');
            foreach ($disciplines as $disc) {
                if ($disc['kind'] === 'class' && in_array($dow, $disc['weekdays'], true)) {
                    $scheduled++;
                }
            }
        }
        $attendedStmt = Database::pdo()->prepare(
            'SELECT COUNT(*) AS n
             FROM sport_logs l
             INNER JOIN sport_disciplines d ON d.id = l.discipline_id
             WHERE l.user_id = :uid AND d.kind = \'class\' AND d.deleted_at IS NULL
               AND l.attended = 1 AND l.log_date BETWEEN :start AND :end'
        );
        $attendedStmt->execute(['uid' => $userId, 'start' => $start, 'end' => $end]);
        $attended = (int) ($attendedStmt->fetch()['n'] ?? 0);

        $done = 0;
        foreach ($exercises as $exercise) {
            if ($exercise['done']) {
                $done++;
            }
        }

        return [
            'km_week' => round($km, 1),
            'classes_done' => $attended,
            'classes_planned' => $scheduled,
            'routine_done' => $hasRoutine ? $done : null,
            'routine_total' => $hasRoutine ? count($exercises) : null,
        ];
    }

    /** @return array{start:string,end:string,days:list<array{date:string,dow:int,label:string,is_today:bool}>} */
    private function weekBounds(string $date): array
    {
        $day = new DateTimeImmutable($date);
        $today = now_local()->format('Y-m-d');
        $monday = $day->modify('-' . ((int) $day->format('N') - 1) . ' days');
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $cursor = $monday->modify('+' . $i . ' days');
            $iso = $cursor->format('Y-m-d');
            $dow = (int) $cursor->format('N');
            $days[] = [
                'date' => $iso,
                'dow' => $dow,
                'label' => self::DAY_SHORT[$dow],
                'is_today' => $iso === $today,
            ];
        }
        return [
            'start' => $days[0]['date'],
            'end' => $days[6]['date'],
            'days' => $days,
        ];
    }

    /** @return array{id:int,kind:string}|null */
    private function findDiscipline(int $userId, int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, kind FROM sport_disciplines
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ? ['id' => (int) $row['id'], 'kind' => (string) $row['kind']] : null;
    }

    private function findExercise(int $userId, int $id): ?int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM sport_exercises WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    private function assertDate(string $date): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Esa fecha no es válida.');
        }
        return $date;
    }

    private function cleanName(string $name, string $emptyMessage): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException($emptyMessage);
        }
        if (mb_strlen($name) > 120) {
            throw new InvalidArgumentException('El nombre es demasiado largo.');
        }
        return $name;
    }

    private function normalizeReps(mixed $raw): int
    {
        $amount = (int) $raw;
        if ($amount < 10 || $amount > self::REPS_GOAL || $amount % 10 !== 0) {
            throw new InvalidArgumentException('Elegí una cantidad de 10 a 100, de 10 en 10.');
        }
        return $amount;
    }

    /** @return array{at_goal:int,percent:int} */
    private function routineRepsGoal(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN reps >= :goal THEN 1 ELSE 0 END) AS at_goal,
                    COALESCE(SUM(reps), 0) AS reps_sum
             FROM sport_exercises
             WHERE user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['uid' => $userId, 'goal' => self::REPS_GOAL]);
        $row = $stmt->fetch() ?: [];
        $total = (int) ($row['total'] ?? 0);
        $sum = (int) ($row['reps_sum'] ?? 0);
        return [
            'at_goal' => (int) ($row['at_goal'] ?? 0),
            'percent' => $total > 0 ? (int) round(($sum / ($total * self::REPS_GOAL)) * 100) : 0,
        ];
    }

    private function optionalDecimal(mixed $raw, float $min, float $max, string $message): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $value = (float) str_replace(',', '.', trim((string) $raw));
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException($message);
        }
        return round($value, 2);
    }

    /** @return array<string, mixed> */
    public function metricsSnapshot(int $userId, ?DateTimeImmutable $today = null, ?int $year = null): array
    {
        $this->ensureTable();
        $today = ($today ?? now_local())->setTime(0, 0);
        $ymd = $today->format('Y-m-d');
        $chartYear = ($year !== null && $year >= 2000 && $year <= 2100) ? $year : (int) $today->format('Y');
        $profile = $this->profile($userId);
        $weights = $this->weights($userId);
        $latest = $weights[0] ?? null;
        $previous = $weights[1] ?? null;
        $weight = $latest ? (float) $latest['weight_kg'] : null;
        $bmi = self::bmi($weight, $profile['height_cm']);
        $weekStart = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
        $weekEnd = $weekStart->modify('+6 days');
        $monthStart = $today->modify('first day of this month');
        $yearStart = $today->setDate($chartYear, 1, 1);
        $yearEnd = $chartYear === (int) $today->format('Y')
            ? $today
            : $today->setDate($chartYear, 12, 31);
        $disciplines = $this->activeDisciplines($userId);
        $exercises = $profile['has_routine'] ? $this->exercisesForDate($userId, $ymd) : [];
        $routineTotal = count($exercises);
        $routineDone = 0;
        foreach ($exercises as $exercise) {
            if ($exercise['done']) {
                $routineDone++;
            }
        }
        $repsGoal = $this->routineRepsGoal($userId);

        return [
            'weight' => $weight,
            'weight_delta' => ($weight !== null && $previous) ? round($weight - (float) $previous['weight_kg'], 2) : null,
            'height_cm' => $profile['height_cm'],
            'bmi' => $bmi,
            'bmi_label' => self::bmiLabel($bmi),
            'has_routine' => $profile['has_routine'],
            'km_week' => $this->sumKm($userId, $weekStart->format('Y-m-d'), $ymd),
            'km_month' => $this->sumKm($userId, $monthStart->format('Y-m-d'), $ymd),
            'km_year' => $this->sumKm($userId, $yearStart->format('Y-m-d'), $yearEnd->format('Y-m-d')),
            'classes_week_done' => $this->countAttended($userId, $weekStart->format('Y-m-d'), $ymd),
            'classes_week_planned' => $this->classSlots($disciplines, $weekStart, $weekEnd),
            'classes_month_done' => $this->countAttended($userId, $monthStart->format('Y-m-d'), $ymd),
            'classes_month_planned' => $this->classSlots($disciplines, $monthStart, $today),
            'classes_year_done' => $this->countAttended($userId, $yearStart->format('Y-m-d'), $yearEnd->format('Y-m-d')),
            'classes_year_planned' => $this->classSlots($disciplines, $yearStart, $yearEnd),
            'routine_done' => $profile['has_routine'] ? $routineDone : null,
            'routine_total' => $profile['has_routine'] ? $routineTotal : null,
            'routine_week_days' => $profile['has_routine'] ? $this->routineCompleteDays($userId, $weekStart, $today) : 0,
            'routine_streak' => $profile['has_routine'] ? $this->routineStreak($userId, $today) : 0,
            'routine_at_100' => $repsGoal['at_goal'],
            'routine_reps_percent' => $repsGoal['percent'],
            'series_year' => $this->sumSeries($userId, $yearStart->format('Y-m-d'), $yearEnd->format('Y-m-d')),
            'discipline_count' => count($disciplines),
            'charts' => $this->trainingCharts($userId, $chartYear, $today, $disciplines),
        ];
    }

    /** @return list<array{id:int,kind:string,weekdays:list<int>}> */
    private function activeDisciplines(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, kind, weekdays FROM sport_disciplines
             WHERE user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['uid' => $userId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'kind' => (string) $row['kind'],
                'weekdays' => array_map('intval', array_filter(explode(',', (string) $row['weekdays']))),
            ];
        }
        return $rows;
    }

    private function sumKm(int $userId, string $start, string $end): float
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(l.quantity), 0) AS km
             FROM sport_logs l
             INNER JOIN sport_disciplines d ON d.id = l.discipline_id
             WHERE l.user_id = :uid AND d.kind = \'distance\' AND d.deleted_at IS NULL
               AND l.log_date BETWEEN :start AND :end'
        );
        $stmt->execute(['uid' => $userId, 'start' => $start, 'end' => $end]);
        return round((float) ($stmt->fetch()['km'] ?? 0), 1);
    }

    private function countAttended(int $userId, string $start, string $end): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) AS n
             FROM sport_logs l
             INNER JOIN sport_disciplines d ON d.id = l.discipline_id
             WHERE l.user_id = :uid AND d.kind = \'class\' AND d.deleted_at IS NULL
               AND l.attended = 1 AND l.log_date BETWEEN :start AND :end'
        );
        $stmt->execute(['uid' => $userId, 'start' => $start, 'end' => $end]);
        return (int) ($stmt->fetch()['n'] ?? 0);
    }

    /** @param list<array{id:int,kind:string,weekdays:list<int>}> $disciplines */
    private function classSlots(array $disciplines, DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $count = 0;
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $dow = (int) $day->format('N');
            foreach ($disciplines as $disc) {
                if ($disc['kind'] === 'class' && in_array($dow, $disc['weekdays'], true)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    private function routineCompleteDays(int $userId, DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return count($this->routineCompleteSet($userId, $start, $end));
    }

    private function routineStreak(int $userId, DateTimeImmutable $today): int
    {
        $done = $this->routineCompleteSet($userId, $today->modify('-365 days'), $today);
        $cursor = $today;
        if (!isset($done[$cursor->format('Y-m-d')])) {
            $cursor = $cursor->modify('-1 day');
        }
        $streak = 0;
        while (isset($done[$cursor->format('Y-m-d')])) {
            $streak++;
            $cursor = $cursor->modify('-1 day');
        }
        return $streak;
    }

    /** @return array<string, true> */
    private function routineCompleteSet(int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $total = $this->exerciseCount($userId);
        if ($total < 1) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT log_date
             FROM sport_exercise_logs
             WHERE user_id = :uid AND is_done = 1 AND log_date BETWEEN :start AND :end
             GROUP BY log_date
             HAVING COUNT(*) >= ' . $total
        );
        $stmt->execute([
            'uid' => $userId,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);
        $set = [];
        foreach ($stmt->fetchAll() as $row) {
            $set[(string) $row['log_date']] = true;
        }
        return $set;
    }

    /**
     * @param list<array{id:int,kind:string,weekdays:list<int>}> $disciplines
     * @return array<string, array<string, mixed>>
     */
    private function trainingCharts(int $userId, int $year, DateTimeImmutable $today, array $disciplines): array
    {
        $lastMonth = $year < (int) $today->format('Y') ? 12 : ($year > (int) $today->format('Y') ? 0 : (int) $today->format('n'));
        $series = $this->monthBuckets(
            'SELECT MONTH(log_date) AS m, COALESCE(SUM(reps), 0) AS n
             FROM sport_exercise_logs
             WHERE user_id = :uid AND is_done = 1 AND YEAR(log_date) = :year
             GROUP BY MONTH(log_date)',
            $userId,
            $year,
            $lastMonth
        );
        $km = $this->monthBuckets(
            'SELECT MONTH(l.log_date) AS m, COALESCE(SUM(l.quantity), 0) AS n
             FROM sport_logs l
             INNER JOIN sport_disciplines d ON d.id = l.discipline_id
             WHERE l.user_id = :uid AND d.kind = \'distance\' AND d.deleted_at IS NULL
               AND YEAR(l.log_date) = :year
             GROUP BY MONTH(l.log_date)',
            $userId,
            $year,
            $lastMonth
        );
        $classesDone = $this->monthBuckets(
            'SELECT MONTH(l.log_date) AS m, COUNT(*) AS n
             FROM sport_logs l
             INNER JOIN sport_disciplines d ON d.id = l.discipline_id
             WHERE l.user_id = :uid AND d.kind = \'class\' AND d.deleted_at IS NULL
               AND l.attended = 1 AND YEAR(l.log_date) = :year
             GROUP BY MONTH(l.log_date)',
            $userId,
            $year,
            $lastMonth
        );
        $classesPlanned = array_fill(0, 12, null);
        for ($month = 1; $month <= $lastMonth; $month++) {
            $start = $today->setDate($year, $month, 1);
            $end = $start->modify('last day of this month');
            if ($year === (int) $today->format('Y') && $month === $lastMonth) {
                $end = $today;
            }
            $classesPlanned[$month - 1] = $this->classSlots($disciplines, $start, $end);
        }
        $pie = $this->seriesShare($userId, $year);
        $weights = $this->weightPoints($userId, $year);

        return [
            'series_bar' => $this->barChart('Series', $series, '#7C83E1'),
            'series_line' => $this->lineChart('Acumulado', $this->cumulative($series), '#78C6B0', null, true, true),
            'km_bar' => $this->barChart('Kilómetros', $km, '#F6D58A'),
            'classes_bar' => [
                'type' => 'bar',
                'data' => [
                    'labels' => self::MONTHS,
                    'datasets' => [
                        [
                            'label' => 'Pautadas',
                            'data' => $classesPlanned,
                            'backgroundColor' => '#E4E7FB',
                            'borderRadius' => 6,
                        ],
                        [
                            'label' => 'Hechas',
                            'data' => $classesDone,
                            'backgroundColor' => '#7C83E1',
                            'borderRadius' => 6,
                        ],
                    ],
                ],
                'options' => [
                    'plugins' => ['legend' => ['position' => 'bottom']],
                    'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
                ],
            ],
            'series_pie' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => $pie['labels'],
                    'datasets' => [[
                        'data' => $pie['data'],
                        'backgroundColor' => $pie['colors'],
                        'borderWidth' => 0,
                    ]],
                ],
                'options' => [
                    'cutout' => '58%',
                    'plugins' => ['legend' => ['position' => 'bottom']],
                ],
            ],
            'weight_line' => $this->lineChart('Peso (kg)', $weights['data'], '#F4B8A8', $weights['labels'], false),
        ];
    }

    /** @param list<int|float|null> $values */
    private function barChart(string $label, array $values, string $color): array
    {
        return [
            'type' => 'bar',
            'data' => [
                'labels' => self::MONTHS,
                'datasets' => [[
                    'label' => $label,
                    'data' => $values,
                    'backgroundColor' => $color,
                    'borderRadius' => 6,
                ]],
            ],
            'options' => [
                'plugins' => ['legend' => ['display' => false]],
                'scales' => ['y' => ['beginAtZero' => true]],
            ],
        ];
    }

    /**
     * @param list<int|float|null> $values
     * @param list<string>|null $labels
     */
    private function lineChart(string $label, array $values, string $color, ?array $labels = null, bool $fill = true, bool $fromZero = false): array
    {
        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels ?? self::MONTHS,
                'datasets' => [[
                    'label' => $label,
                    'data' => $values,
                    'borderColor' => $color,
                    'backgroundColor' => $this->fadeHex($color, 0.18),
                    'fill' => $fill,
                    'tension' => 0.35,
                    'pointRadius' => 2,
                ]],
            ],
            'options' => [
                'plugins' => ['legend' => ['display' => false]],
                'scales' => ['y' => ['beginAtZero' => $fromZero]],
            ],
        ];
    }

    /** @param list<int|float|null> $values @return list<int|float|null> */
    private function cumulative(array $values): array
    {
        $sum = 0;
        $out = [];
        foreach ($values as $value) {
            if ($value === null) {
                $out[] = null;
                continue;
            }
            $sum += $value;
            $out[] = $sum;
        }
        return $out;
    }

    /** @return list<int|float|null> */
    private function monthBuckets(string $sql, int $userId, int $year, int $lastMonth): array
    {
        $buckets = array_fill(0, 12, null);
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['uid' => $userId, 'year' => $year]);
        foreach ($stmt->fetchAll() as $row) {
            $month = (int) $row['m'];
            if ($month >= 1 && $month <= $lastMonth) {
                $buckets[$month - 1] = round((float) $row['n'], 1);
            }
        }
        for ($i = 0; $i < $lastMonth; $i++) {
            if ($buckets[$i] === null) {
                $buckets[$i] = 0;
            }
        }
        return $buckets;
    }

    /** @return array{labels:list<string>,data:list<int>,colors:list<string>} */
    private function seriesShare(int $userId, int $year): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.name, COALESCE(SUM(l.reps), 0) AS n
             FROM sport_exercise_logs l
             INNER JOIN sport_exercises e ON e.id = l.exercise_id
             WHERE l.user_id = :uid AND l.is_done = 1 AND YEAR(l.log_date) = :year
             GROUP BY e.id, e.name
             HAVING n > 0
             ORDER BY n DESC'
        );
        $stmt->execute(['uid' => $userId, 'year' => $year]);
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            return ['labels' => ['Sin series'], 'data' => [1], 'colors' => ['#E6E8F0']];
        }
        $labels = [];
        $data = [];
        $colors = [];
        $rest = 0;
        foreach ($rows as $index => $row) {
            if ($index < 8) {
                $labels[] = (string) $row['name'];
                $data[] = (int) $row['n'];
                $colors[] = self::CHART_COLORS[$index % count(self::CHART_COLORS)];
            } else {
                $rest += (int) $row['n'];
            }
        }
        if ($rest > 0) {
            $labels[] = 'Otros';
            $data[] = $rest;
            $colors[] = '#CBD0E0';
        }
        return ['labels' => $labels, 'data' => $data, 'colors' => $colors];
    }

    /** @return array{labels:list<string>,data:list<float|null>} */
    private function weightPoints(int $userId, int $year): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT weighed_on, weight_kg FROM sport_weights
             WHERE user_id = :uid AND YEAR(weighed_on) = :year
             ORDER BY weighed_on ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId, 'year' => $year]);
        $labels = [];
        $data = [];
        foreach ($stmt->fetchAll() as $row) {
            $labels[] = (new DateTimeImmutable((string) $row['weighed_on']))->format('d/m');
            $data[] = round((float) $row['weight_kg'], 1);
        }
        if ($labels === []) {
            return ['labels' => ['Sin pesadas'], 'data' => [null]];
        }
        return ['labels' => $labels, 'data' => $data];
    }

    private function fadeHex(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return 'rgba(124, 131, 225, ' . $alpha . ')';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . $alpha . ')';
    }

    /** @return array<string, int> */
    private function seriesByDay(int $userId, string $start, string $end): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT log_date, COALESCE(SUM(reps), 0) AS series
             FROM sport_exercise_logs
             WHERE user_id = :uid AND is_done = 1 AND log_date BETWEEN :start AND :end
             GROUP BY log_date'
        );
        $stmt->execute(['uid' => $userId, 'start' => $start, 'end' => $end]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(string) $row['log_date']] = (int) $row['series'];
        }
        return $map;
    }

    private function sumSeries(int $userId, string $start, string $end): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(reps), 0) AS series
             FROM sport_exercise_logs
             WHERE user_id = :uid AND is_done = 1 AND log_date BETWEEN :start AND :end'
        );
        $stmt->execute(['uid' => $userId, 'start' => $start, 'end' => $end]);
        return (int) ($stmt->fetch()['series'] ?? 0);
    }

    private function exerciseCount(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) AS n FROM sport_exercises WHERE user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['uid' => $userId]);
        return (int) ($stmt->fetch()['n'] ?? 0);
    }

    /** @return list<int> */
    private static function normalizeWeekdays(mixed $raw): array
    {
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
}
