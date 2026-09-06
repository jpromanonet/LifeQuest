<?php

declare(strict_types=1);

final class MilestoneService
{
    public function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $pdo = Database::pdo();
        $exists = $pdo->query("SHOW TABLES LIKE 'milestones'")->fetch();
        if (!$exists) {
            $pdo->exec(
                "CREATE TABLE milestones (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  user_id BIGINT UNSIGNED NOT NULL,
                  title VARCHAR(255) NOT NULL,
                  milestone_date DATE NOT NULL,
                  notes TEXT NULL,
                  is_done TINYINT(1) NOT NULL DEFAULT 0,
                  completed_at DATETIME NULL,
                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  deleted_at DATETIME NULL,
                  KEY idx_milestones_user_date (user_id, milestone_date, deleted_at),
                  KEY idx_milestones_user_pending (user_id, is_done, milestone_date, deleted_at),
                  CONSTRAINT fk_milestones_user FOREIGN KEY (user_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $done = true;
    }

    /** @return list<array<string,mixed>> */
    public function listAll(int $userId): array
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT id, title, milestone_date, notes, is_done, completed_at, created_at
             FROM milestones
             WHERE user_id = :uid AND deleted_at IS NULL
             ORDER BY milestone_date ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Hitos del día de hoy o vencidos sin marcar (bloquean la home).
     *
     * @return list<array<string,mixed>>
     */
    public function pendingDue(int $userId, ?string $onOrBefore = null): array
    {
        $this->ensureTable();
        $date = $onOrBefore ?? now_local()->format('Y-m-d');
        $stmt = Database::pdo()->prepare(
            'SELECT id, title, milestone_date, notes, is_done, completed_at
             FROM milestones
             WHERE user_id = :uid AND deleted_at IS NULL
               AND is_done = 0 AND milestone_date <= :d
             ORDER BY milestone_date ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId, 'd' => $date]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM milestones WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array{title:string,milestone_date:string,notes?:?string} $data */
    public function create(int $userId, array $data): int
    {
        $this->ensureTable();
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('El título es obligatorio.');
        }
        $date = (string) ($data['milestone_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Fecha inválida.');
        }
        $notes = $data['notes'] ?? null;
        if (is_string($notes)) {
            $notes = trim($notes);
            $notes = $notes === '' ? null : $notes;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO milestones (user_id, title, milestone_date, notes)
             VALUES (:uid, :title, :d, :notes)'
        );
        $stmt->execute([
            'uid' => $userId,
            'title' => mb_substr($title, 0, 255),
            'd' => $date,
            'notes' => $notes,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array{title?:string,milestone_date?:string,notes?:?string} $data */
    public function update(int $userId, int $id, array $data): void
    {
        $this->ensureTable();
        $row = $this->find($userId, $id);
        if ($row === null) {
            throw new RuntimeException('Hito no encontrado.');
        }

        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : (string) $row['title'];
        if ($title === '') {
            throw new InvalidArgumentException('El título es obligatorio.');
        }
        $date = array_key_exists('milestone_date', $data)
            ? (string) $data['milestone_date']
            : (string) $row['milestone_date'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Fecha inválida.');
        }
        $notes = array_key_exists('notes', $data) ? $data['notes'] : ($row['notes'] ?? null);
        if (is_string($notes)) {
            $notes = trim($notes);
            $notes = $notes === '' ? null : $notes;
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE milestones
             SET title = :title, milestone_date = :d, notes = :notes, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute([
            'title' => mb_substr($title, 0, 255),
            'd' => $date,
            'notes' => $notes,
            'id' => $id,
            'uid' => $userId,
        ]);
    }

    public function markDone(int $userId, int $id): void
    {
        $this->ensureTable();
        $row = $this->find($userId, $id);
        if ($row === null) {
            throw new RuntimeException('Hito no encontrado.');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE milestones
             SET is_done = 1, completed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    public function markPending(int $userId, int $id): void
    {
        $this->ensureTable();
        $row = $this->find($userId, $id);
        if ($row === null) {
            throw new RuntimeException('Hito no encontrado.');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE milestones
             SET is_done = 0, completed_at = NULL, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    public function delete(int $userId, int $id): void
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare(
            'UPDATE milestones SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    /**
     * @return array{upcoming:int,pending_due:int,done:int,total:int}
     */
    public function stats(int $userId): array
    {
        $today = now_local()->format('Y-m-d');
        $rows = $this->listAll($userId);
        $upcoming = 0;
        $pendingDue = 0;
        $done = 0;
        foreach ($rows as $row) {
            if ((int) ($row['is_done'] ?? 0) === 1) {
                $done++;
                continue;
            }
            if ((string) $row['milestone_date'] <= $today) {
                $pendingDue++;
            } else {
                $upcoming++;
            }
        }
        return [
            'upcoming' => $upcoming,
            'pending_due' => $pendingDue,
            'done' => $done,
            'total' => count($rows),
        ];
    }
}
