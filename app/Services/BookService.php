<?php

declare(strict_types=1);

final class BookService
{
    /** @return list<array<string, mixed>> */
    public function listByYear(int $userId, int $year): array
    {
        return $this->list($userId, $year);
    }

    /**
     * @param ?int $year null = todos los años
     * @return list<array<string, mixed>>
     */
    public function list(int $userId, ?int $year = null): array
    {
        $sql = 'SELECT * FROM books
             WHERE user_id = :uid AND deleted_at IS NULL';
        $params = ['uid' => $userId];
        if ($year !== null) {
            $sql .= ' AND year_num = :year';
            $params['year'] = $year;
        }
        $sql .= ' ORDER BY
                year_num DESC,
                CASE status WHEN \'finished\' THEN 2 WHEN \'reading\' THEN 1 ELSE 0 END,
                planned_month IS NULL, planned_month ASC, title ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return list<int> */
    public function yearsWithBooks(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT DISTINCT year_num FROM books
             WHERE user_id = :uid AND deleted_at IS NULL
             ORDER BY year_num DESC'
        );
        $stmt->execute(['uid' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param array<string, mixed> $data */
    public function create(int $userId, array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Título obligatorio');
        }
        $status = (string) ($data['status'] ?? 'planned');
        if (!in_array($status, ['planned', 'reading', 'finished'], true)) {
            $status = 'planned';
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO books (
                user_id, title, year_num, planned_month, finished_at, pages, pages_read,
                status, goodreads_url, notes
             ) VALUES (
                :uid, :title, :year, :month, :finished, :pages, :pages_read,
                :status, :goodreads, :notes
             )'
        );
        $stmt->execute([
            'uid' => $userId,
            'title' => $title,
            'year' => (int) ($data['year'] ?? date('Y')),
            'month' => ($data['planned_month'] ?? '') !== '' ? (int) $data['planned_month'] : null,
            'finished' => ($data['finished_at'] ?? '') !== '' ? $data['finished_at'] : null,
            'pages' => ($data['pages'] ?? '') !== '' ? (int) $data['pages'] : null,
            'pages_read' => (int) ($data['pages_read'] ?? 0),
            'status' => $status,
            'goodreads' => ($data['goodreads_url'] ?? '') !== '' ? $data['goodreads_url'] : null,
            'notes' => $data['notes'] ?? null,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $userId, int $id, array $data): void
    {
        $existing = $this->find($userId, $id);
        if ($existing === null) {
            throw new RuntimeException('Libro no encontrado');
        }
        $status = (string) ($data['status'] ?? $existing['status']);
        if (!in_array($status, ['planned', 'reading', 'finished'], true)) {
            $status = $existing['status'];
        }

        Database::pdo()->prepare(
            'UPDATE books SET
                title = :title,
                year_num = :year,
                planned_month = :month,
                finished_at = :finished,
                pages = :pages,
                pages_read = :pages_read,
                status = :status,
                goodreads_url = :goodreads,
                notes = :notes
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        )->execute([
            'title' => trim((string) ($data['title'] ?? $existing['title'])),
            'year' => (int) ($data['year'] ?? $existing['year_num']),
            'month' => array_key_exists('planned_month', $data)
                ? (($data['planned_month'] === '' || $data['planned_month'] === null) ? null : (int) $data['planned_month'])
                : $existing['planned_month'],
            'finished' => array_key_exists('finished_at', $data)
                ? (($data['finished_at'] === '' || $data['finished_at'] === null) ? null : $data['finished_at'])
                : $existing['finished_at'],
            'pages' => array_key_exists('pages', $data)
                ? (($data['pages'] === '' || $data['pages'] === null) ? null : (int) $data['pages'])
                : $existing['pages'],
            'pages_read' => (int) ($data['pages_read'] ?? $existing['pages_read']),
            'status' => $status,
            'goodreads' => array_key_exists('goodreads_url', $data)
                ? (($data['goodreads_url'] === '' || $data['goodreads_url'] === null) ? null : $data['goodreads_url'])
                : $existing['goodreads_url'],
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $existing['notes'],
            'id' => $id,
            'uid' => $userId,
        ]);
    }

    public function softDelete(int $userId, int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE books SET deleted_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Libro no encontrado');
        }
    }

    public const DEFAULT_TARGET = 12;
    public const GOAL_SOURCE = 'books';

    /** Meta de libros del año: la que fijó el usuario, o 12 por defecto. */
    public function annualTarget(int $userId, int $year): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT target_value FROM goals
             WHERE user_id = :uid AND goal_key = :key AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'key' => 'books_' . $year]);
        $target = (int) ($stmt->fetchColumn() ?: 0);

        return $target > 0 ? $target : self::DEFAULT_TARGET;
    }

    public function setAnnualTarget(int $userId, int $year, int $target): void
    {
        $target = max(1, $target);
        Database::pdo()->prepare(
            'UPDATE goals SET target_value = :target
             WHERE user_id = :uid AND goal_key = :key AND deleted_at IS NULL'
        )->execute(['target' => $target, 'uid' => $userId, 'key' => 'books_' . $year]);

        $this->ensureAnnualGoal($userId, $year);
    }

    /**
     * @param ?int $year null = todos los años
     * @return array{finished:int,reading:int,planned:int,total:int,pages:int,pages_read:int}
     */
    public function statsForYear(int $userId, ?int $year = null): array
    {
        $sql = 'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = \'finished\' THEN 1 ELSE 0 END) AS finished,
                SUM(CASE WHEN status = \'reading\' THEN 1 ELSE 0 END) AS reading,
                SUM(CASE WHEN status = \'planned\' THEN 1 ELSE 0 END) AS planned,
                COALESCE(SUM(CASE WHEN status = \'finished\' THEN COALESCE(pages, pages_read, 0) ELSE pages_read END), 0) AS pages,
                COALESCE(SUM(
                    CASE
                        WHEN status = \'finished\' THEN GREATEST(COALESCE(pages_read, 0), COALESCE(pages, 0))
                        ELSE COALESCE(pages_read, 0)
                    END
                ), 0) AS pages_read
             FROM books
             WHERE user_id = :uid AND deleted_at IS NULL';
        $params = ['uid' => $userId];
        if ($year !== null) {
            $sql .= ' AND year_num = :year';
            $params['year'] = $year;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'finished' => (int) ($row['finished'] ?? 0),
            'reading' => (int) ($row['reading'] ?? 0),
            'planned' => (int) ($row['planned'] ?? 0),
            'pages' => (int) ($row['pages'] ?? 0),
            'pages_read' => (int) ($row['pages_read'] ?? 0),
        ];
    }

    /**
     * Garantiza el objetivo fijo "Libros" en Educación para el año dado.
     * Meta mínima de 12 libros; el avance se sincroniza con los libros terminados.
     */
    public function ensureAnnualGoal(int $userId, int $year): void
    {
        $pdo = Database::pdo();

        $areaStmt = $pdo->prepare(
            'SELECT id FROM life_areas
             WHERE user_id = :uid AND area_key = \'annual_educacion\' AND deleted_at IS NULL
             LIMIT 1'
        );
        $areaStmt->execute(['uid' => $userId]);
        $areaId = $areaStmt->fetchColumn();
        if ($areaId === false) {
            return;
        }
        $areaId = (int) $areaId;

        $stats = $this->statsForYear($userId, $year);
        $finished = $stats['finished'];
        $goalKey = 'books_' . $year;

        $find = $pdo->prepare(
            'SELECT id, target_value FROM goals
             WHERE user_id = :uid AND goal_key = :key AND deleted_at IS NULL LIMIT 1'
        );
        $find->execute(['uid' => $userId, 'key' => $goalKey]);
        $existing = $find->fetch();

        $existingTarget = (int) ($existing['target_value'] ?? 0);
        $target = $existingTarget > 0 ? $existingTarget : self::DEFAULT_TARGET;
        $percent = round(min(100, ($finished / $target) * 100), 2);
        $status = $finished >= $target ? 'completed' : 'active';
        $completedAt = $finished >= $target ? gmdate('Y-m-d H:i:s') : null;
        $title = 'Leer ' . $target . ' libros en el año';
        $dueDate = sprintf('%04d-12-31', $year);

        if ($existing) {
            $pdo->prepare(
                'UPDATE goals SET
                    title = :title,
                    area_id = :area_id,
                    horizon = \'anual\',
                    period_year = :year,
                    progress_mode = \'quantity\',
                    target_value = :target,
                    current_value = :current,
                    unit = \'libros\',
                    progress_percent = :pct,
                    status = :status,
                    due_date = :due_date,
                    completed_at = :completed_at,
                    external_system = :source,
                    external_url = :url
                 WHERE id = :id AND user_id = :uid'
            )->execute([
                'title' => $title,
                'area_id' => $areaId,
                'year' => $year,
                'target' => $target,
                'current' => $finished,
                'pct' => $percent,
                'status' => $status,
                'due_date' => $dueDate,
                'completed_at' => $completedAt,
                'source' => self::GOAL_SOURCE,
                'url' => '/books?year=' . $year,
                'id' => (int) $existing['id'],
                'uid' => $userId,
            ]);
            return;
        }

        $pdo->prepare(
            'INSERT INTO goals (
                user_id, goal_key, title, goal_type, area_id, horizon, period_year,
                status, priority, progress_mode, progress_percent, target_value,
                current_value, unit, due_date, completed_at, external_system, external_url, sort_order
             ) VALUES (
                :uid, :key, :title, \'goal\', :area_id, \'anual\', :year,
                :status, \'high\', \'quantity\', :pct, :target,
                :current, \'libros\', :due_date, :completed_at, :source, :url, 0
             )'
        )->execute([
            'uid' => $userId,
            'key' => $goalKey,
            'title' => $title,
            'area_id' => $areaId,
            'year' => $year,
            'status' => $status,
            'pct' => $percent,
            'target' => $target,
            'current' => $finished,
            'due_date' => $dueDate,
            'completed_at' => $completedAt,
            'source' => self::GOAL_SOURCE,
            'url' => '/books?year=' . $year,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM books WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
