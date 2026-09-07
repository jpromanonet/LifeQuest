<?php

declare(strict_types=1);

final class FriendService
{
    public function ensureTables(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $pdo = Database::pdo();
        if (!$pdo->query("SHOW TABLES LIKE 'friends'")->fetch()) {
            $pdo->exec(
                "CREATE TABLE friends (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  user_id BIGINT UNSIGNED NOT NULL,
                  name VARCHAR(160) NOT NULL,
                  sort_order INT NOT NULL DEFAULT 0,
                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  deleted_at DATETIME NULL,
                  KEY idx_friends_user (user_id, deleted_at, sort_order),
                  CONSTRAINT fk_friends_user FOREIGN KEY (user_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        if (!$pdo->query("SHOW TABLES LIKE 'friend_talk_logs'")->fetch()) {
            $pdo->exec(
                "CREATE TABLE friend_talk_logs (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  user_id BIGINT UNSIGNED NOT NULL,
                  friend_id BIGINT UNSIGNED NOT NULL,
                  talk_date DATE NOT NULL,
                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  UNIQUE KEY uq_friend_talk (user_id, friend_id, talk_date),
                  KEY idx_friend_talk_user_date (user_id, talk_date),
                  CONSTRAINT fk_friend_talk_user FOREIGN KEY (user_id) REFERENCES users(id),
                  CONSTRAINT fk_friend_talk_friend FOREIGN KEY (friend_id) REFERENCES friends(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $done = true;
    }

    /** @return list<array<string,mixed>> */
    public function listActive(int $userId): array
    {
        $this->ensureTables();
        $stmt = Database::pdo()->prepare(
            'SELECT id, name, sort_order, created_at
             FROM friends
             WHERE user_id = :uid AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        $this->ensureTables();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM friends WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, string $name): int
    {
        $this->ensureTables();
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        $pdo = Database::pdo();
        $max = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) FROM friends WHERE user_id = :uid AND deleted_at IS NULL'
        );
        $max->execute(['uid' => $userId]);
        $stmt = $pdo->prepare(
            'INSERT INTO friends (user_id, name, sort_order) VALUES (:uid, :name, :ord)'
        );
        $stmt->execute([
            'uid' => $userId,
            'name' => mb_substr($name, 0, 160),
            'ord' => (int) $max->fetchColumn() + 1,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $userId, int $id, string $name): void
    {
        $this->ensureTables();
        if ($this->find($userId, $id) === null) {
            throw new RuntimeException('Amigo/a no encontrado.');
        }
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE friends SET name = :name, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['name' => mb_substr($name, 0, 160), 'id' => $id, 'uid' => $userId]);
    }

    public function delete(int $userId, int $id): void
    {
        $this->ensureTables();
        $stmt = Database::pdo()->prepare(
            'UPDATE friends SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    /**
     * Amigo/a del día: si ya hubo charla hoy, queda fija; si no, el/la que hace más.
     *
     * @return array{id:int,name:string}|null
     */
    public function suggestionForDate(int $userId, ?string $onDate = null): ?array
    {
        $date = $onDate ?: now_local()->format('Y-m-d');
        $locked = $this->talkedOnDate($userId, $date);
        if ($locked !== null) {
            return $locked;
        }
        return $this->pickNext($userId);
    }

    /**
     * Quien ya quedó registrado para esa fecha (si hubo charla).
     *
     * @return array{id:int,name:string}|null
     */
    public function talkedOnDate(int $userId, string $date): ?array
    {
        $this->ensureTables();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT t.id AS talk_id, f.id, f.name
             FROM friend_talk_logs t
             INNER JOIN friends f ON f.id = t.friend_id AND f.user_id = t.user_id AND f.deleted_at IS NULL
             WHERE t.user_id = :uid AND t.talk_date = :d
             ORDER BY t.id ASC'
        );
        $stmt->execute(['uid' => $userId, 'd' => $date]);
        $rows = $stmt->fetchAll() ?: [];
        if ($rows === []) {
            return null;
        }
        // Por si quedó más de una charla el mismo día (bug anterior): dejar solo la primera.
        if (count($rows) > 1) {
            $keepId = (int) $rows[0]['talk_id'];
            $pdo->prepare(
                'DELETE FROM friend_talk_logs
                 WHERE user_id = :uid AND talk_date = :d AND id <> :keep'
            )->execute(['uid' => $userId, 'd' => $date, 'keep' => $keepId]);
        }
        return ['id' => (int) $rows[0]['id'], 'name' => (string) $rows[0]['name']];
    }

    /**
     * Quien hace más tiempo que no aparece (o nunca). No usa charlas del día actual.
     *
     * @return array{id:int,name:string}|null
     */
    public function nextSuggestion(int $userId, ?string $onDate = null): ?array
    {
        return $this->suggestionForDate($userId, $onDate);
    }

    /** @return array{id:int,name:string}|null */
    private function pickNext(int $userId): ?array
    {
        $this->ensureTables();
        $friends = $this->listActive($userId);
        if ($friends === []) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT friend_id, MAX(talk_date) AS last_talk
             FROM friend_talk_logs
             WHERE user_id = :uid
             GROUP BY friend_id'
        );
        $stmt->execute(['uid' => $userId]);
        $last = [];
        foreach ($stmt->fetchAll() as $row) {
            $last[(int) $row['friend_id']] = (string) $row['last_talk'];
        }

        $best = null;
        $bestKey = null;
        foreach ($friends as $friend) {
            $id = (int) $friend['id'];
            $lastDate = $last[$id] ?? '0000-00-00';
            $key = $lastDate . '|' . str_pad((string) ($friend['sort_order'] ?? 0), 6, '0', STR_PAD_LEFT) . '|' . $id;
            if ($bestKey === null || $key < $bestKey) {
                $bestKey = $key;
                $best = ['id' => $id, 'name' => (string) $friend['name']];
            }
        }
        return $best;
    }

    public function recordTalk(int $userId, int $friendId, string $date): void
    {
        $this->ensureTables();
        // Una sola persona por día: si ya hay charla, no rotar a otra.
        $existing = $this->talkedOnDate($userId, $date);
        if ($existing !== null) {
            return;
        }
        if ($this->find($userId, $friendId) === null) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO friend_talk_logs (user_id, friend_id, talk_date)
             VALUES (:uid, :fid, :d)
             ON DUPLICATE KEY UPDATE talk_date = VALUES(talk_date)'
        );
        $stmt->execute(['uid' => $userId, 'fid' => $friendId, 'd' => $date]);
    }

    public function clearTalk(int $userId, int $friendId, string $date): void
    {
        $this->clearTalksOnDate($userId, $date);
    }

    public function clearTalksOnDate(int $userId, string $date): void
    {
        $this->ensureTables();
        $stmt = Database::pdo()->prepare(
            'DELETE FROM friend_talk_logs
             WHERE user_id = :uid AND talk_date = :d'
        );
        $stmt->execute(['uid' => $userId, 'd' => $date]);
    }

    /**
     * @return array{
     *   total:int,
     *   talked_week:int,
     *   talks_week:int,
     *   talked_month:int,
     *   talks_month:int,
     *   talked_year:int,
     *   talks_year:int,
     *   never:int,
     *   coverage_month:int,
     *   days_week:int,
     *   streak:int,
     *   last_talk_date:?string,
     *   suggested:?array{id:int,name:string}
     * }
     */
    public function stats(int $userId): array
    {
        $this->ensureTables();
        $friends = $this->listActive($userId);
        $total = count($friends);
        $suggested = $this->nextSuggestion($userId);
        $empty = [
            'total' => 0,
            'talked_week' => 0,
            'talks_week' => 0,
            'talked_month' => 0,
            'talks_month' => 0,
            'talked_year' => 0,
            'talks_year' => 0,
            'never' => 0,
            'coverage_month' => 0,
            'days_week' => 0,
            'streak' => 0,
            'last_talk_date' => null,
            'suggested' => $suggested,
        ];
        if ($total === 0) {
            return $empty;
        }

        $today = now_local();
        $todayYmd = $today->format('Y-m-d');
        $weekStart = $today->modify('monday this week')->format('Y-m-d');
        $monthStart = $today->format('Y-m-01');
        $yearStart = $today->format('Y-01-01');
        $ids = array_map(static fn (array $f): int => (int) $f['id'], $friends);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $pdo = Database::pdo();
        $lastStmt = $pdo->prepare(
            "SELECT friend_id, MAX(talk_date) AS last_talk
             FROM friend_talk_logs
             WHERE user_id = ? AND friend_id IN ($placeholders)
             GROUP BY friend_id"
        );
        $lastStmt->execute(array_merge([$userId], $ids));
        $lastByFriend = [];
        $lastTalkDate = null;
        foreach ($lastStmt->fetchAll() as $row) {
            $d = (string) $row['last_talk'];
            $lastByFriend[(int) $row['friend_id']] = $d;
            if ($lastTalkDate === null || $d > $lastTalkDate) {
                $lastTalkDate = $d;
            }
        }

        $never = 0;
        $talkedMonth = 0;
        $talkedYear = 0;
        foreach ($ids as $id) {
            $last = $lastByFriend[$id] ?? null;
            if ($last === null) {
                $never++;
                continue;
            }
            if ($last >= $monthStart) {
                $talkedMonth++;
            }
            if ($last >= $yearStart) {
                $talkedYear++;
            }
        }

        $countDistinct = $pdo->prepare(
            'SELECT COUNT(DISTINCT friend_id)
             FROM friend_talk_logs
             WHERE user_id = :uid AND talk_date >= :from'
        );
        $countTalks = $pdo->prepare(
            'SELECT COUNT(*)
             FROM friend_talk_logs
             WHERE user_id = :uid AND talk_date >= :from'
        );
        $countDays = $pdo->prepare(
            'SELECT COUNT(DISTINCT talk_date)
             FROM friend_talk_logs
             WHERE user_id = :uid AND talk_date >= :from AND talk_date <= :to'
        );

        $countDistinct->execute(['uid' => $userId, 'from' => $weekStart]);
        $talkedWeek = (int) $countDistinct->fetchColumn();
        $countTalks->execute(['uid' => $userId, 'from' => $weekStart]);
        $talksWeek = (int) $countTalks->fetchColumn();
        $countDays->execute(['uid' => $userId, 'from' => $weekStart, 'to' => $todayYmd]);
        $daysWeek = (int) $countDays->fetchColumn();

        $countTalks->execute(['uid' => $userId, 'from' => $monthStart]);
        $talksMonth = (int) $countTalks->fetchColumn();
        $countTalks->execute(['uid' => $userId, 'from' => $yearStart]);
        $talksYear = (int) $countTalks->fetchColumn();

        $daysStmt = $pdo->prepare(
            'SELECT DISTINCT talk_date
             FROM friend_talk_logs
             WHERE user_id = :uid AND talk_date >= :from AND talk_date <= :to
             ORDER BY talk_date DESC'
        );
        $daysStmt->execute(['uid' => $userId, 'from' => $yearStart, 'to' => $todayYmd]);
        $talkDays = [];
        foreach ($daysStmt->fetchAll() as $row) {
            $talkDays[(string) $row['talk_date']] = true;
        }

        $streak = 0;
        $probe = $today;
        while (true) {
            $d = $probe->format('Y-m-d');
            if ($d < $yearStart) {
                break;
            }
            if (!isset($talkDays[$d])) {
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
            'total' => $total,
            'talked_week' => $talkedWeek,
            'talks_week' => $talksWeek,
            'talked_month' => $talkedMonth,
            'talks_month' => $talksMonth,
            'talked_year' => $talkedYear,
            'talks_year' => $talksYear,
            'never' => $never,
            'coverage_month' => $total > 0 ? (int) round(($talkedMonth / $total) * 100) : 0,
            'days_week' => $daysWeek,
            'streak' => $streak,
            'last_talk_date' => $lastTalkDate,
            'suggested' => $suggested,
        ];
    }
}
