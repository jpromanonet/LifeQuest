<?php

declare(strict_types=1);

final class ScheduleService
{
    public const DAY_START = 6 * 60;
    public const DAY_END = 24 * 60;
    public const SNAP = 60;

    public const DAY_LABELS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public const DAY_SHORT = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mié',
        4 => 'Jue',
        5 => 'Vie',
        6 => 'Sáb',
        7 => 'Dom',
    ];

    /** @var array<string, string> */
    public const COLORS = [
        'lavender' => 'Lavanda',
        'lilac' => 'Lila',
        'sky' => 'Cielo',
        'aqua' => 'Agua',
        'mint' => 'Menta',
        'sage' => 'Salvia',
        'yellow' => 'Sol',
        'peach' => 'Durazno',
        'coral' => 'Coral',
        'rose' => 'Rosa',
        'sand' => 'Arena',
        'primary' => 'Índigo',
    ];

    public function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $pdo = Database::pdo();
        $exists = $pdo->query("SHOW TABLES LIKE 'schedule_blocks'")->fetch();
        if (!$exists) {
            $pdo->exec(
                "CREATE TABLE schedule_blocks (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  user_id BIGINT UNSIGNED NOT NULL,
                  weekday TINYINT UNSIGNED NOT NULL,
                  title VARCHAR(160) NOT NULL,
                  notes VARCHAR(500) NULL,
                  start_minute SMALLINT UNSIGNED NOT NULL,
                  end_minute SMALLINT UNSIGNED NOT NULL,
                  color VARCHAR(24) NOT NULL DEFAULT 'lavender',
                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  deleted_at DATETIME NULL,
                  KEY idx_schedule_user_day (user_id, weekday, deleted_at),
                  CONSTRAINT fk_schedule_blocks_user FOREIGN KEY (user_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $this->snapExistingToHours();
        $done = true;
    }

    /** Bloques viejos de 30 minutos pasan a la hora más cercana. */
    private function snapExistingToHours(): void
    {
        $rows = Database::pdo()->query(
            'SELECT id, start_minute, end_minute
             FROM schedule_blocks
             WHERE deleted_at IS NULL
               AND (MOD(start_minute, 60) <> 0 OR MOD(end_minute, 60) <> 0)'
        )->fetchAll();
        if (!$rows) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE schedule_blocks
             SET start_minute = :start_minute, end_minute = :end_minute, updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        foreach ($rows as $row) {
            $start = (int) (round(((int) $row['start_minute']) / 60) * 60);
            $end = (int) (round(((int) $row['end_minute']) / 60) * 60);
            $start = max(self::DAY_START, min(self::DAY_END - self::SNAP, $start));
            $end = max($start + self::SNAP, min(self::DAY_END, $end));
            $stmt->execute([
                'start_minute' => $start,
                'end_minute' => $end,
                'id' => (int) $row['id'],
            ]);
        }
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    public function board(int $userId): array
    {
        $this->ensureTable();
        $byDay = [];
        for ($d = 1; $d <= 7; $d++) {
            $byDay[$d] = [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id, weekday, title, notes, start_minute, end_minute, color
             FROM schedule_blocks
             WHERE user_id = :uid AND deleted_at IS NULL
             ORDER BY weekday ASC, start_minute ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $day = (int) $row['weekday'];
            if ($day < 1 || $day > 7) {
                continue;
            }
            $byDay[$day][] = $this->present($row);
        }
        foreach ($byDay as $day => $blocks) {
            $byDay[$day] = self::layout($blocks);
        }
        return $byDay;
    }

    /** @param array<string, mixed> $data */
    public function create(int $userId, array $data): array
    {
        $this->ensureTable();
        $fields = $this->normalize($data);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO schedule_blocks
                (user_id, weekday, title, notes, start_minute, end_minute, color)
             VALUES
                (:uid, :weekday, :title, :notes, :start_minute, :end_minute, :color)'
        );
        $stmt->execute([
            'uid' => $userId,
            'weekday' => $fields['weekday'],
            'title' => $fields['title'],
            'notes' => $fields['notes'],
            'start_minute' => $fields['start_minute'],
            'end_minute' => $fields['end_minute'],
            'color' => $fields['color'],
        ]);
        $id = (int) Database::pdo()->lastInsertId();
        $block = $this->find($userId, $id);
        if ($block === null) {
            throw new RuntimeException('No se pudo leer el bloque creado.');
        }
        return $block;
    }

    /** @param array<string, mixed> $data */
    public function update(int $userId, int $id, array $data): array
    {
        $this->ensureTable();
        if ($this->find($userId, $id) === null) {
            throw new InvalidArgumentException('No encontré ese bloque.');
        }
        $fields = $this->normalize($data);
        $stmt = Database::pdo()->prepare(
            'UPDATE schedule_blocks
             SET weekday = :weekday, title = :title, notes = :notes,
                 start_minute = :start_minute, end_minute = :end_minute, color = :color,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute([
            'weekday' => $fields['weekday'],
            'title' => $fields['title'],
            'notes' => $fields['notes'],
            'start_minute' => $fields['start_minute'],
            'end_minute' => $fields['end_minute'],
            'color' => $fields['color'],
            'id' => $id,
            'uid' => $userId,
        ]);
        $block = $this->find($userId, $id);
        if ($block === null) {
            throw new RuntimeException('No se pudo leer el bloque.');
        }
        return $block;
    }

    /**
     * Copia el bloque a otros días de la semana fija. No modifica el original.
     *
     * @param mixed $repeatDays
     * @return list<array<string, mixed>>
     */
    public function repeatOnDays(int $userId, int $sourceId, mixed $repeatDays): array
    {
        $days = self::normalizeWeekdays($repeatDays);
        $source = $this->find($userId, $sourceId);
        if ($source === null || $days === []) {
            return [];
        }

        $origin = (int) $source['weekday'];
        $titleKey = mb_strtolower(trim((string) $source['title']));
        $taken = $this->weekdaysWithTitle($userId, $titleKey);
        $copies = [];
        foreach ($days as $dow) {
            if ($dow === $origin || isset($taken[$dow])) {
                continue;
            }
            $copies[] = $this->create($userId, [
                'title' => $source['title'],
                'notes' => $source['notes'],
                'weekday' => $dow,
                'start_minute' => $source['start_minute'],
                'end_minute' => $source['end_minute'],
                'color' => $source['color'],
            ]);
            $taken[$dow] = true;
        }

        return $copies;
    }

    /** @return array<int, true> */
    private function weekdaysWithTitle(int $userId, string $titleKey): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT weekday, title
             FROM schedule_blocks
             WHERE user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['uid' => $userId]);
        $taken = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = mb_strtolower(trim((string) $row['title']));
            if ($key === $titleKey) {
                $taken[(int) $row['weekday']] = true;
            }
        }
        return $taken;
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

    public function delete(int $userId, int $id): void
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'UPDATE schedule_blocks
             SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        if ($stmt->rowCount() < 1) {
            throw new InvalidArgumentException('No encontré ese bloque.');
        }
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT id, weekday, title, notes, start_minute, end_minute, color
             FROM schedule_blocks
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ? $this->present($row) : null;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    public static function layout(array $blocks): array
    {
        usort($blocks, static function (array $a, array $b): int {
            $byStart = ((int) $a['start_minute']) <=> ((int) $b['start_minute']);
            return $byStart !== 0 ? $byStart : ((int) $a['id']) <=> ((int) $b['id']);
        });
        $laneEnds = [];
        foreach ($blocks as $i => $block) {
            $start = (int) $block['start_minute'];
            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $start) {
                $lane++;
            }
            $laneEnds[$lane] = (int) $block['end_minute'];
            $blocks[$i]['lane'] = $lane;
        }
        foreach ($blocks as $i => $block) {
            $max = (int) $block['lane'];
            foreach ($blocks as $other) {
                if (self::overlaps($block, $other)) {
                    $max = max($max, (int) $other['lane']);
                }
            }
            $blocks[$i]['lanes'] = $max + 1;
        }
        return $blocks;
    }

    public static function formatMinute(int $minute): string
    {
        if ($minute >= 24 * 60) {
            return '00:00';
        }
        $minute = max(0, $minute);
        return sprintf('%d:%02d', intdiv($minute, 60), $minute % 60);
    }

    /** @return list<array{minute:int,label:string}> */
    public static function slots(bool $includeEnd = true): array
    {
        $slots = [];
        $last = $includeEnd ? self::DAY_END : self::DAY_END - self::SNAP;
        for ($minute = self::DAY_START; $minute <= $last; $minute += self::SNAP) {
            $slots[] = ['minute' => $minute, 'label' => self::formatMinute($minute)];
        }
        return $slots;
    }

    /** @param array<string, mixed> $data
     *  @return array{weekday:int,title:string,notes:?string,start_minute:int,end_minute:int,color:string}
     */
    private function normalize(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Poné un nombre al bloque.');
        }
        if (mb_strlen($title) > 120) {
            throw new InvalidArgumentException('El nombre es demasiado largo.');
        }
        $weekday = (int) ($data['weekday'] ?? 0);
        if ($weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException('Elegí un día de la semana.');
        }
        $start = (int) ($data['start_minute'] ?? 0);
        $end = (int) ($data['end_minute'] ?? 0);
        $start = (int) (round($start / self::SNAP) * self::SNAP);
        $end = (int) (round($end / self::SNAP) * self::SNAP);
        if ($start < self::DAY_START || $end > self::DAY_END || $end - $start < self::SNAP) {
            throw new InvalidArgumentException('El horario tiene que ir de 6:00 a 00:00, en horas enteras.');
        }
        $color = (string) ($data['color'] ?? 'lavender');
        if (!isset(self::COLORS[$color])) {
            $color = 'lavender';
        }
        $notes = trim((string) ($data['notes'] ?? ''));
        if ($notes === '') {
            $notes = null;
        } elseif (mb_strlen($notes) > 500) {
            throw new InvalidArgumentException('La nota es demasiado larga.');
        }
        return [
            'weekday' => $weekday,
            'title' => $title,
            'notes' => $notes,
            'start_minute' => $start,
            'end_minute' => $end,
            'color' => $color,
        ];
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $start = (int) $row['start_minute'];
        $end = (int) $row['end_minute'];
        return [
            'id' => (int) $row['id'],
            'weekday' => (int) $row['weekday'],
            'title' => (string) $row['title'],
            'notes' => (string) ($row['notes'] ?? ''),
            'start_minute' => $start,
            'end_minute' => $end,
            'color' => isset(self::COLORS[(string) $row['color']]) ? (string) $row['color'] : 'lavender',
            'start_label' => self::formatMinute($start),
            'end_label' => self::formatMinute($end),
        ];
    }

    /** @param array<string, mixed> $a @param array<string, mixed> $b */
    private static function overlaps(array $a, array $b): bool
    {
        return (int) $a['start_minute'] < (int) $b['end_minute']
            && (int) $b['start_minute'] < (int) $a['end_minute'];
    }
}
