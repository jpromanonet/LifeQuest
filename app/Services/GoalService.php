<?php

declare(strict_types=1);

final class GoalService
{
    private const VALID_STATUSES = ['idea', 'planned', 'active', 'paused', 'completed', 'cancelled', 'archived'];
    private const VALID_PRIORITIES = ['low', 'medium', 'high', 'critical'];

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function list(int $userId, array $filters = []): array
    {
        $sql = 'SELECT g.*,
                       la.name AS area_name, la.color AS area_color, la.area_key,
                       ia.name AS impact_area_name, ia.color AS impact_area_color
                FROM goals g
                LEFT JOIN life_areas la ON la.id = g.area_id AND la.user_id = g.user_id
                LEFT JOIN life_areas ia ON ia.id = g.impact_area_id AND ia.user_id = g.user_id
                WHERE g.user_id = :user_id
                  AND g.deleted_at IS NULL';
        $params = ['user_id' => $userId];

        if (isset($filters['year']) && $filters['year'] !== '' && $filters['year'] !== null) {
            $sql .= ' AND g.period_year = :year';
            $params['year'] = (int) $filters['year'];
        }
        if (!empty($filters['area_id'])) {
            $sql .= ' AND g.area_id = :area_id';
            $params['area_id'] = (int) $filters['area_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND g.status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= ' AND g.priority = :priority';
            $params['priority'] = (string) $filters['priority'];
        }
        if (!empty($filters['goal_type'])) {
            $sql .= ' AND g.goal_type = :goal_type';
            $params['goal_type'] = (string) $filters['goal_type'];
        }
        if (!empty($filters['horizon'])) {
            $sql .= ' AND g.horizon = :horizon';
            $params['horizon'] = (string) $filters['horizon'];
        }
        if (!empty($filters['exclude_horizon']) || !empty($filters['annual_only'])) {
            $sql .= ' AND g.horizon <> \'largo_plazo\'';
        }
        if (!empty($filters['area_scope'])) {
            $sql .= ' AND (la.scope = :area_scope OR (la.id IS NULL AND :area_scope2 = \'annual\'))';
            $params['area_scope'] = (string) $filters['area_scope'];
            $params['area_scope2'] = (string) $filters['area_scope'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (g.title LIKE :q OR g.description LIKE :q OR g.goal_key LIKE :q)';
            $params['q'] = '%' . (string) $filters['q'] . '%';
        }

        $sql .= ' ORDER BY
                    FIELD(g.priority, \'critical\', \'high\', \'medium\', \'low\'),
                    g.sort_order ASC,
                    g.id ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'hydrateGoal'], $stmt->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT g.*,
                    la.name AS area_name, la.color AS area_color, la.area_key,
                    ia.name AS impact_area_name, ia.color AS impact_area_color
             FROM goals g
             LEFT JOIN life_areas la ON la.id = g.area_id AND la.user_id = g.user_id
             LEFT JOIN life_areas ia ON ia.id = g.impact_area_id AND ia.user_id = g.user_id
             WHERE g.user_id = :user_id AND g.id = :id AND g.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrateGoal($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findByKey(int $userId, string $key): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT g.*,
                    la.name AS area_name, la.color AS area_color, la.area_key,
                    ia.name AS impact_area_name, ia.color AS impact_area_color
             FROM goals g
             LEFT JOIN life_areas la ON la.id = g.area_id AND la.user_id = g.user_id
             LEFT JOIN life_areas ia ON ia.id = g.impact_area_id AND ia.user_id = g.user_id
             WHERE g.user_id = :user_id AND g.goal_key = :goal_key AND g.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'goal_key' => $key]);
        $row = $stmt->fetch();
        return $row ? $this->hydrateGoal($row) : null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $userId, array $data): int
    {
        $status = (string) ($data['status'] ?? 'planned');
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid goal status');
        }
        $priority = (string) ($data['priority'] ?? 'medium');
        if (!in_array($priority, self::VALID_PRIORITIES, true)) {
            throw new InvalidArgumentException('Invalid goal priority');
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO goals (
                user_id, goal_key, title, description, goal_type, area_id, impact_area_id,
                parent_goal_id, series_id, horizon, period_year, period_quarter, period_month,
                status, priority, progress_mode, progress_percent, target_value, current_value,
                unit, start_date, due_date, success_criteria, motivation, next_action,
                external_system, external_url, source_system, source_page, source_text,
                source_checked, sort_order, completed_at
             ) VALUES (
                :user_id, :goal_key, :title, :description, :goal_type, :area_id, :impact_area_id,
                :parent_goal_id, :series_id, :horizon, :period_year, :period_quarter, :period_month,
                :status, :priority, :progress_mode, :progress_percent, :target_value, :current_value,
                :unit, :start_date, :due_date, :success_criteria, :motivation, :next_action,
                :external_system, :external_url, :source_system, :source_page, :source_text,
                :source_checked, :sort_order, :completed_at
             )'
        );
        $stmt->execute([
            'user_id' => $userId,
            'goal_key' => $data['goal_key'] ?? null,
            'title' => (string) ($data['title'] ?? ''),
            'description' => $data['description'] ?? null,
            'goal_type' => $data['goal_type'] ?? 'goal',
            'area_id' => $data['area_id'] ?? null,
            'impact_area_id' => $data['impact_area_id'] ?? null,
            'parent_goal_id' => $data['parent_goal_id'] ?? null,
            'series_id' => $data['series_id'] ?? null,
            'horizon' => $data['horizon'] ?? 'anual',
            'period_year' => $data['period_year'] ?? null,
            'period_quarter' => $data['period_quarter'] ?? null,
            'period_month' => $data['period_month'] ?? null,
            'status' => $status,
            'priority' => $priority,
            'progress_mode' => $data['progress_mode'] ?? 'months',
            'progress_percent' => $data['progress_percent'] ?? 0,
            'target_value' => $data['target_value'] ?? null,
            'current_value' => $data['current_value'] ?? null,
            'unit' => $data['unit'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'success_criteria' => $data['success_criteria'] ?? null,
            'motivation' => $data['motivation'] ?? null,
            'next_action' => $data['next_action'] ?? null,
            'external_system' => $data['external_system'] ?? null,
            'external_url' => $data['external_url'] ?? null,
            'source_system' => $data['source_system'] ?? 'lifequest',
            'source_page' => $data['source_page'] ?? null,
            'source_text' => $data['source_text'] ?? null,
            'source_checked' => $data['source_checked'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'completed_at' => $status === 'completed' ? ($data['completed_at'] ?? gmdate('Y-m-d H:i:s')) : null,
        ]);

        $goalId = (int) Database::pdo()->lastInsertId();
        $mode = (string) ($data['progress_mode'] ?? 'months');
        if ($mode === 'months') {
            $this->ensureMonthMode($userId, $goalId);
        } elseif ($mode === 'binary') {
            $done = (float) ($data['progress_percent'] ?? 0) >= 100 || ($data['status'] ?? '') === 'completed';
            $this->setBinary($userId, $goalId, $done);
        }
        return $goalId;
    }

    /** @param array<string, mixed> $data */
    public function update(int $userId, int $id, array $data): void
    {
        $existing = $this->find($userId, $id);
        if ($existing === null) {
            throw new RuntimeException('Goal not found');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $status = array_key_exists('status', $data) ? (string) $data['status'] : (string) $existing['status'];
            if (!in_array($status, self::VALID_STATUSES, true)) {
                throw new InvalidArgumentException('Invalid goal status');
            }
            $priority = array_key_exists('priority', $data) ? (string) $data['priority'] : (string) $existing['priority'];
            if (!in_array($priority, self::VALID_PRIORITIES, true)) {
                throw new InvalidArgumentException('Invalid goal priority');
            }

            $completedAt = $existing['completed_at'];
            if ($status === 'completed' && (string) $existing['status'] !== 'completed') {
                $completedAt = $data['completed_at'] ?? gmdate('Y-m-d H:i:s');
            } elseif ($status !== 'completed') {
                $completedAt = null;
            }

            $stmt = $pdo->prepare(
                'UPDATE goals SET
                    goal_key = :goal_key,
                    title = :title,
                    description = :description,
                    goal_type = :goal_type,
                    area_id = :area_id,
                    impact_area_id = :impact_area_id,
                    parent_goal_id = :parent_goal_id,
                    series_id = :series_id,
                    horizon = :horizon,
                    period_year = :period_year,
                    period_quarter = :period_quarter,
                    period_month = :period_month,
                    status = :status,
                    priority = :priority,
                    progress_mode = :progress_mode,
                    progress_percent = :progress_percent,
                    target_value = :target_value,
                    current_value = :current_value,
                    unit = :unit,
                    start_date = :start_date,
                    due_date = :due_date,
                    success_criteria = :success_criteria,
                    motivation = :motivation,
                    next_action = :next_action,
                    external_system = :external_system,
                    external_url = :external_url,
                    source_system = :source_system,
                    source_page = :source_page,
                    source_text = :source_text,
                    source_checked = :source_checked,
                    sort_order = :sort_order,
                    completed_at = :completed_at
                 WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
            );
            $stmt->execute([
                'goal_key' => array_key_exists('goal_key', $data) ? $data['goal_key'] : $existing['goal_key'],
                'title' => $data['title'] ?? $existing['title'],
                'description' => array_key_exists('description', $data) ? $data['description'] : $existing['description'],
                'goal_type' => $data['goal_type'] ?? $existing['goal_type'],
                'area_id' => array_key_exists('area_id', $data) ? $data['area_id'] : $existing['area_id'],
                'impact_area_id' => array_key_exists('impact_area_id', $data) ? $data['impact_area_id'] : $existing['impact_area_id'],
                'parent_goal_id' => array_key_exists('parent_goal_id', $data) ? $data['parent_goal_id'] : $existing['parent_goal_id'],
                'series_id' => array_key_exists('series_id', $data) ? $data['series_id'] : $existing['series_id'],
                'horizon' => $data['horizon'] ?? $existing['horizon'],
                'period_year' => array_key_exists('period_year', $data) ? $data['period_year'] : $existing['period_year'],
                'period_quarter' => array_key_exists('period_quarter', $data) ? $data['period_quarter'] : $existing['period_quarter'],
                'period_month' => array_key_exists('period_month', $data) ? $data['period_month'] : $existing['period_month'],
                'status' => $status,
                'priority' => $priority,
                'progress_mode' => array_key_exists('progress_mode', $data)
                    ? $data['progress_mode']
                    : ($existing['progress_mode'] ?? 'months'),
                'progress_percent' => array_key_exists('progress_percent', $data)
                    ? $data['progress_percent']
                    : $existing['progress_percent'],
                'target_value' => array_key_exists('target_value', $data) ? $data['target_value'] : $existing['target_value'],
                'current_value' => array_key_exists('current_value', $data) ? $data['current_value'] : $existing['current_value'],
                'unit' => array_key_exists('unit', $data) ? $data['unit'] : $existing['unit'],
                'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : $existing['start_date'],
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $existing['due_date'],
                'success_criteria' => array_key_exists('success_criteria', $data) ? $data['success_criteria'] : $existing['success_criteria'],
                'motivation' => array_key_exists('motivation', $data) ? $data['motivation'] : $existing['motivation'],
                'next_action' => array_key_exists('next_action', $data) ? $data['next_action'] : $existing['next_action'],
                'external_system' => array_key_exists('external_system', $data) ? $data['external_system'] : $existing['external_system'],
                'external_url' => array_key_exists('external_url', $data) ? $data['external_url'] : $existing['external_url'],
                'source_system' => $data['source_system'] ?? $existing['source_system'],
                'source_page' => array_key_exists('source_page', $data) ? $data['source_page'] : $existing['source_page'],
                'source_text' => array_key_exists('source_text', $data) ? $data['source_text'] : $existing['source_text'],
                'source_checked' => array_key_exists('source_checked', $data) ? $data['source_checked'] : $existing['source_checked'],
                'sort_order' => (int) ($data['sort_order'] ?? $existing['sort_order']),
                'completed_at' => $completedAt,
                'id' => $id,
                'user_id' => $userId,
            ]);

            if ($status !== (string) $existing['status']) {
                $hist = $pdo->prepare(
                    'INSERT INTO goal_status_history (goal_id, from_status, to_status, note)
                     VALUES (:goal_id, :from_status, :to_status, :note)'
                );
                $hist->execute([
                    'goal_id' => $id,
                    'from_status' => $existing['status'],
                    'to_status' => $status,
                    'note' => $data['status_note'] ?? null,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function softDelete(int $userId, int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE goals SET deleted_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Goal not found');
        }
    }

    /** @param array<string, mixed> $data */
    public function updateProgress(int $userId, int $id, array $data): void
    {
        $existing = $this->find($userId, $id);
        if ($existing === null) {
            throw new RuntimeException('Goal not found');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $percent = array_key_exists('progress_percent', $data)
                ? $data['progress_percent']
                : $existing['progress_percent'];
            $currentValue = array_key_exists('current_value', $data)
                ? $data['current_value']
                : $existing['current_value'];

            $upd = $pdo->prepare(
                'UPDATE goals
                 SET progress_percent = :progress_percent,
                     current_value = :current_value
                 WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
            );
            $upd->execute([
                'progress_percent' => $percent,
                'current_value' => $currentValue,
                'id' => $id,
                'user_id' => $userId,
            ]);

            $log = $pdo->prepare(
                'INSERT INTO goal_progress_logs (
                    goal_id, log_date, progress_percent, value_delta, comment, evidence_url
                 ) VALUES (
                    :goal_id, :log_date, :progress_percent, :value_delta, :comment, :evidence_url
                 )'
            );
            $log->execute([
                'goal_id' => $id,
                'log_date' => $data['log_date'] ?? now_local()->format('Y-m-d'),
                'progress_percent' => $percent,
                'value_delta' => $data['value_delta'] ?? null,
                'comment' => $data['comment'] ?? null,
                'evidence_url' => $data['evidence_url'] ?? null,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function milestones(int $goalId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM goal_milestones
             WHERE goal_id = :goal_id AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['goal_id' => $goalId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function actions(int $goalId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM goal_actions
             WHERE goal_id = :goal_id AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['goal_id' => $goalId]);
        return $stmt->fetchAll();
    }

    /**
     * Sections ordered like the annual template, each with its goals.
     *
     * @return list<array{area: array<string, mixed>, goals: list<array<string, mixed>>}>
     */
    public function groupedByArea(int $userId, int $year): array
    {
        $templateKeys = AnnualPlanService::TEMPLATE_AREA_KEYS;

        $areasStmt = Database::pdo()->prepare(
            'SELECT * FROM life_areas
             WHERE user_id = :user_id AND deleted_at IS NULL AND scope = \'annual\'
             ORDER BY sort_order ASC, id ASC'
        );
        $areasStmt->execute(['user_id' => $userId]);
        $areas = $areasStmt->fetchAll();
        $byKey = [];
        foreach ($areas as $area) {
            $byKey[$area['area_key']] = $area;
        }

        $goals = $this->list($userId, [
            'year' => $year,
            'exclude_horizon' => true,
            'area_scope' => 'annual',
        ]);
        $goalsByArea = [];
        foreach ($goals as $goal) {
            $areaId = $goal['area_id'] !== null ? (int) $goal['area_id'] : 0;
            $goalsByArea[$areaId][] = $goal;
        }

        $ordered = [];
        foreach ($templateKeys as $key) {
            if (!isset($byKey[$key])) {
                continue;
            }
            $area = $byKey[$key];
            $ordered[] = [
                'area' => $area,
                'goals' => $goalsByArea[(int) $area['id']] ?? [],
            ];
            unset($byKey[$key], $goalsByArea[(int) $area['id']]);
        }
        foreach ($byKey as $area) {
            $ordered[] = [
                'area' => $area,
                'goals' => $goalsByArea[(int) $area['id']] ?? [],
            ];
            unset($goalsByArea[(int) $area['id']]);
        }
        if (!empty($goalsByArea[0])) {
            $ordered[] = [
                'area' => self::unassignedArea(),
                'goals' => $goalsByArea[0],
            ];
        }

        return $ordered;
    }

    /**
     * Área virtual para objetivos anuales (o de horizonte) sin life_area asignada.
     *
     * @return array{id:int,area_key:string,name:string,color:string}
     */
    public static function unassignedArea(): array
    {
        return [
            'id' => 0,
            'area_key' => 'sin_area',
            'name' => 'Sin área',
            'color' => '#1A1A1A',
        ];
    }

    /**
     * Long-term horizon goals grouped by horizon domains.
     *
     * @return list<array{area: array<string, mixed>, goals: list<array<string, mixed>>}>
     */
    public function groupedByHorizonArea(int $userId): array
    {
        $templateKeys = [
            'hz_laborales',
            'hz_salud',
            'hz_financieros',
            'hz_patrimonial',
            'hz_academicos',
            'hz_hobbies',
        ];

        $areasStmt = Database::pdo()->prepare(
            'SELECT * FROM life_areas
             WHERE user_id = :user_id AND deleted_at IS NULL AND scope = \'horizon\'
             ORDER BY sort_order ASC, id ASC'
        );
        $areasStmt->execute(['user_id' => $userId]);
        $areas = $areasStmt->fetchAll();
        $byKey = [];
        foreach ($areas as $area) {
            $byKey[$area['area_key']] = $area;
        }

        $goals = $this->list($userId, ['horizon' => 'largo_plazo']);
        $goalsByArea = [];
        foreach ($goals as $goal) {
            $areaId = $goal['area_id'] !== null ? (int) $goal['area_id'] : 0;
            $goalsByArea[$areaId][] = $goal;
        }

        $ordered = [];
        foreach ($templateKeys as $key) {
            if (!isset($byKey[$key])) {
                continue;
            }
            $area = $byKey[$key];
            $ordered[] = [
                'area' => $area,
                'goals' => $goalsByArea[(int) $area['id']] ?? [],
            ];
            unset($byKey[$key], $goalsByArea[(int) $area['id']]);
        }
        foreach ($byKey as $area) {
            $ordered[] = [
                'area' => $area,
                'goals' => $goalsByArea[(int) $area['id']] ?? [],
            ];
            unset($goalsByArea[(int) $area['id']]);
        }
        if (!empty($goalsByArea[0])) {
            $ordered[] = [
                'area' => self::unassignedArea(),
                'goals' => $goalsByArea[0],
            ];
        }

        return $ordered;
    }

    /** @return list<array<string, mixed>> */
    public function focusGoals(int $userId, int $limit = 5): array
    {
        $year = (int) now_local()->format('Y');
        $limit = max(1, $limit);

        $stmt = Database::pdo()->prepare(
            'SELECT g.*, la.name AS area_name, la.color AS area_color, la.area_key
             FROM goals g
             LEFT JOIN life_areas la ON la.id = g.area_id AND la.user_id = g.user_id
             WHERE g.user_id = :user_id
               AND g.deleted_at IS NULL
               AND g.horizon <> \'largo_plazo\'
               AND g.status IN (\'active\', \'planned\')
               AND g.period_year = :year
             ORDER BY
                FIELD(g.status, \'active\', \'planned\'),
                FIELD(g.priority, \'critical\', \'high\', \'medium\', \'low\'),
                g.sort_order ASC,
                g.id ASC
             LIMIT ' . $limit
        );
        $stmt->execute(['user_id' => $userId, 'year' => $year]);
        return array_map([$this, 'hydrateGoal'], $stmt->fetchAll());
    }

    /** @return array<string, int> */
    public function countsByStatus(int $userId, ?int $year = null): array
    {
        $counts = array_fill_keys(self::VALID_STATUSES, 0);
        $sql = 'SELECT status, COUNT(*) AS c
                FROM goals
                WHERE user_id = :user_id AND deleted_at IS NULL';
        $params = ['user_id' => $userId];
        if ($year !== null) {
            $sql .= ' AND period_year = :year AND horizon <> \'largo_plazo\'';
            $params['year'] = $year;
        }
        $sql .= ' GROUP BY status';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }
        return $counts;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function hydrateGoal(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['progress_percent'] = (float) $row['progress_percent'];
        return $row;
    }

    /** @return array{months: array<int,bool>, checked: int, percent: float} */
    public function monthProgress(int $userId, int $goalId): array
    {
        $goal = $this->find($userId, $goalId);
        if ($goal === null) {
            throw new RuntimeException('Goal not found');
        }

        return $this->monthProgressForId($goalId);
    }

    /** @return array{months: array<int,bool>, checked: int, percent: float} */
    private function monthProgressForId(int $goalId): array
    {
        $map = MonthProgress::emptyMap();
        $stmt = Database::pdo()->prepare(
            'SELECT month_num FROM goal_month_checks WHERE goal_id = :gid AND is_checked = 1'
        );
        $stmt->execute(['gid' => $goalId]);
        foreach ($stmt->fetchAll() as $row) {
            $m = (int) $row['month_num'];
            if ($m >= 1 && $m <= 12) {
                $map[$m] = true;
            }
        }
        return MonthProgress::summary($map);
    }

    /**
     * @param list<array<string, mixed>> $goals
     * @return list<array<string, mixed>>
     */
    public function attachMonthProgress(int $userId, array $goals): array
    {
        foreach ($goals as &$goal) {
            $id = (int) $goal['id'];
            $mode = (string) ($goal['progress_mode'] ?? 'months');
            if ($mode === 'binary') {
                $done = (float) ($goal['progress_percent'] ?? 0) >= 100 || ($goal['status'] ?? '') === 'completed';
                $goal['progress_percent'] = $done ? 100.0 : 0.0;
                $goal['months_checked'] = $done ? 12 : 0;
                $goal['month_checks'] = MonthProgress::emptyMap();
                continue;
            }
            if ($mode === 'quantity') {
                $target = (float) ($goal['target_value'] ?? 0);
                $current = (float) ($goal['current_value'] ?? 0);
                $goal['progress_percent'] = $target > 0 ? round(min(100, ($current / $target) * 100), 2) : (float) ($goal['progress_percent'] ?? 0);
                $goal['month_checks'] = MonthProgress::emptyMap();
                $goal['months_checked'] = 0;
                continue;
            }
            $this->ensureMonthMode($userId, $id);
            $progress = $this->monthProgressForId($id);
            $goal['progress_percent'] = $progress['percent'];
            $goal['month_checks'] = $progress['months'];
            $goal['months_checked'] = $progress['checked'];
        }
        unset($goal);
        return $goals;
    }

    public function toggleMonth(int $userId, int $goalId, int $month): array
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Mes invalido');
        }
        $goal = $this->find($userId, $goalId);
        if ($goal === null) {
            throw new RuntimeException('Goal not found');
        }

        $pdo = Database::pdo();
        $find = $pdo->prepare(
            'SELECT id, is_checked FROM goal_month_checks WHERE goal_id = :gid AND month_num = :m LIMIT 1'
        );
        $find->execute(['gid' => $goalId, 'm' => $month]);
        $row = $find->fetch();

        if ($row) {
            $new = (int) $row['is_checked'] ? 0 : 1;
            $pdo->prepare(
                'UPDATE goal_month_checks SET is_checked = :c, updated_at = UTC_TIMESTAMP() WHERE id = :id'
            )->execute(['c' => $new, 'id' => (int) $row['id']]);
        } else {
            $pdo->prepare(
                'INSERT INTO goal_month_checks (goal_id, month_num, is_checked) VALUES (:gid, :m, 1)'
            )->execute(['gid' => $goalId, 'm' => $month]);
        }

        $progress = $this->monthProgress($userId, $goalId);
        $status = $goal['status'];
        $completedAt = $goal['completed_at'];
        if ($progress['checked'] >= 12) {
            $status = 'completed';
            $completedAt = gmdate('Y-m-d H:i:s');
        } elseif ($status === 'completed' && $progress['checked'] < 12) {
            $status = 'active';
            $completedAt = null;
        }

        $pdo->prepare(
            'UPDATE goals
             SET progress_mode = \'months\',
                 progress_percent = :pct,
                 status = :status,
                 completed_at = :completed_at
             WHERE id = :id AND user_id = :uid'
        )->execute([
            'pct' => $progress['percent'],
            'status' => $status,
            'completed_at' => $completedAt,
            'id' => $goalId,
            'uid' => $userId,
        ]);

        $progress['status'] = $status;
        return $progress;
    }

    /** Sync percent from existing checks (or seed checks from percent once). */
    public function ensureMonthMode(int $userId, int $goalId): void
    {
        $goal = $this->find($userId, $goalId);
        if ($goal === null) {
            return;
        }
        $mode = (string) ($goal['progress_mode'] ?? 'months');
        if ($mode === 'binary' || $mode === 'quantity') {
            return;
        }
        $pdo = Database::pdo();
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM goal_month_checks WHERE goal_id = :gid');
        $countStmt->execute(['gid' => $goalId]);
        $hasRows = (int) $countStmt->fetchColumn() > 0;

        if (!$hasRows) {
            $pct = (float) ($goal['progress_percent'] ?? 0);
            $monthsToCheck = (int) round(($pct / 100) * 12);
            $monthsToCheck = max(0, min(12, $monthsToCheck));
            $ins = $pdo->prepare(
                'INSERT INTO goal_month_checks (goal_id, month_num, is_checked) VALUES (:gid, :m, 1)'
            );
            for ($m = 1; $m <= $monthsToCheck; $m++) {
                $ins->execute(['gid' => $goalId, 'm' => $m]);
            }
        }

        $progress = $this->monthProgress($userId, $goalId);
        $pdo->prepare(
            'UPDATE goals SET progress_mode = \'months\', progress_percent = :pct
             WHERE id = :id AND user_id = :uid'
        )->execute(['pct' => $progress['percent'], 'id' => $goalId, 'uid' => $userId]);
    }

    /**
     * El horizonte es siempre binario: se marca como logrado o no.
     * Normaliza modo, porcentaje y limpia los meses heredados.
     */
    public function forceBinaryHorizon(int $userId): void
    {
        $pdo = Database::pdo();

        $pdo->prepare(
            'DELETE c FROM goal_month_checks c
             INNER JOIN goals g ON g.id = c.goal_id
             WHERE g.user_id = :uid AND g.horizon = \'largo_plazo\''
        )->execute(['uid' => $userId]);

        $pdo->prepare(
            'UPDATE goals
             SET progress_mode = \'binary\',
                 progress_percent = CASE WHEN status = \'completed\' THEN 100 ELSE 0 END,
                 target_value = NULL,
                 current_value = NULL,
                 unit = NULL
             WHERE user_id = :uid AND deleted_at IS NULL AND horizon = \'largo_plazo\''
        )->execute(['uid' => $userId]);
    }

    /** @return array{done:bool,percent:float,status:string} */
    public function toggleBinary(int $userId, int $goalId): array
    {
        $goal = $this->find($userId, $goalId);
        if ($goal === null) {
            throw new RuntimeException('Goal not found');
        }

        $done = (float) ($goal['progress_percent'] ?? 0) >= 100
            || ($goal['status'] ?? '') === 'completed';
        $newDone = !$done;
        $percent = $newDone ? 100.0 : 0.0;
        $status = $newDone ? 'completed' : 'active';
        $completedAt = $newDone ? gmdate('Y-m-d H:i:s') : null;

        Database::pdo()->prepare(
            'UPDATE goals
             SET progress_mode = \'binary\',
                 progress_percent = :pct,
                 status = :status,
                 completed_at = :completed_at
             WHERE id = :id AND user_id = :uid'
        )->execute([
            'pct' => $percent,
            'status' => $status,
            'completed_at' => $completedAt,
            'id' => $goalId,
            'uid' => $userId,
        ]);

        return ['done' => $newDone, 'percent' => $percent, 'status' => $status];
    }

    /**
     * Progreso por unidades (ej. libros leídos sobre una meta).
     *
     * @return array{current:float,target:float,percent:float,status:string,unit:string}
     */
    public function setQuantity(
        int $userId,
        int $goalId,
        ?float $current = null,
        ?float $target = null,
        ?string $unit = null
    ): array {
        $goal = $this->find($userId, $goalId);
        if ($goal === null) {
            throw new RuntimeException('Goal not found');
        }

        $target = max(0.0, $target ?? (float) ($goal['target_value'] ?? 0));
        $current = max(0.0, $current ?? (float) ($goal['current_value'] ?? 0));
        $unit = $unit !== null && $unit !== '' ? $unit : ((string) ($goal['unit'] ?? '') ?: 'unidades');
        $percent = $target > 0 ? round(min(100, ($current / $target) * 100), 2) : 0.0;

        $status = (string) $goal['status'];
        $completedAt = $goal['completed_at'];
        if ($target > 0 && $current >= $target) {
            $status = 'completed';
            $completedAt = $completedAt ?: gmdate('Y-m-d H:i:s');
        } elseif ($status === 'completed') {
            $status = 'active';
            $completedAt = null;
        }

        Database::pdo()->prepare(
            'UPDATE goals
             SET progress_mode = \'quantity\',
                 target_value = :target,
                 current_value = :current,
                 unit = :unit,
                 progress_percent = :pct,
                 status = :status,
                 completed_at = :completed_at
             WHERE id = :id AND user_id = :uid'
        )->execute([
            'target' => $target,
            'current' => $current,
            'unit' => $unit,
            'pct' => $percent,
            'status' => $status,
            'completed_at' => $completedAt,
            'id' => $goalId,
            'uid' => $userId,
        ]);

        return [
            'current' => $current,
            'target' => $target,
            'percent' => $percent,
            'status' => $status,
            'unit' => $unit,
        ];
    }

    public function setBinary(int $userId, int $goalId, bool $done): array
    {
        $goal = $this->find($userId, $goalId);
        if ($goal === null) {
            throw new RuntimeException('Goal not found');
        }
        $percent = $done ? 100.0 : 0.0;
        $status = $done ? 'completed' : ((string) ($goal['status'] === 'completed' ? 'active' : $goal['status']));
        if ($done) {
            $status = 'completed';
        } elseif ($status === 'completed') {
            $status = 'active';
        }

        Database::pdo()->prepare(
            'UPDATE goals
             SET progress_mode = \'binary\',
                 progress_percent = :pct,
                 status = :status,
                 completed_at = :completed_at
             WHERE id = :id AND user_id = :uid'
        )->execute([
            'pct' => $percent,
            'status' => $status,
            'completed_at' => $done ? gmdate('Y-m-d H:i:s') : null,
            'id' => $goalId,
            'uid' => $userId,
        ]);

        return ['done' => $done, 'percent' => $percent, 'status' => $status];
    }

    /**
     * Crea (o reutiliza) una serie para vincular copias del mismo objetivo en distintos años.
     */
    public function ensureSeries(int $userId, array $goal): int
    {
        if (!empty($goal['series_id'])) {
            return (int) $goal['series_id'];
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO goal_series (user_id, series_key, title_template, area_id, target_value, unit)
             VALUES (:uid, :key, :title, :area_id, :target, :unit)'
        );
        $stmt->execute([
            'uid' => $userId,
            'key' => 'rpt_' . $userId . '_' . (int) ($goal['id'] ?? 0) . '_' . bin2hex(random_bytes(4)),
            'title' => (string) ($goal['title'] ?? ''),
            'area_id' => $goal['area_id'] ?? null,
            'target' => $goal['target_value'] ?? null,
            'unit' => $goal['unit'] ?? null,
        ]);
        $seriesId = (int) Database::pdo()->lastInsertId();

        if (!empty($goal['id'])) {
            Database::pdo()->prepare(
                'UPDATE goals SET series_id = :sid WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
            )->execute([
                'sid' => $seriesId,
                'id' => (int) $goal['id'],
                'uid' => $userId,
            ]);
        }

        return $seriesId;
    }

    public function seriesHasYear(int $userId, int $seriesId, int $year): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM goals
             WHERE user_id = :uid AND series_id = :sid AND period_year = :year
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'sid' => $seriesId, 'year' => $year]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Copia un objetivo anual a otro año, sin progreso ni meses tildados.
     */
    public function copyToYear(int $userId, array $source, int $year, int $seriesId): int
    {
        $baseYear = (int) ($source['period_year'] ?? $year);
        $mode = (string) ($source['progress_mode'] ?? 'months');
        $status = (string) ($source['status'] ?? 'planned');
        if (in_array($status, ['completed', 'cancelled', 'archived'], true)) {
            $status = 'planned';
        }

        return $this->create($userId, [
            'goal_key' => null,
            'title' => $source['title'] ?? '',
            'description' => $source['description'] ?? null,
            'goal_type' => $source['goal_type'] ?? 'goal',
            'area_id' => $source['area_id'] ?? null,
            'impact_area_id' => $source['impact_area_id'] ?? null,
            'series_id' => $seriesId,
            'horizon' => $source['horizon'] ?? 'anual',
            'period_year' => $year,
            'status' => $status,
            'priority' => $source['priority'] ?? 'medium',
            'progress_mode' => $mode,
            'progress_percent' => 0,
            'target_value' => $source['target_value'] ?? null,
            'current_value' => $mode === 'quantity' ? 0 : null,
            'unit' => $source['unit'] ?? null,
            'start_date' => $this->shiftDateYear($source['start_date'] ?? null, $baseYear, $year),
            'due_date' => $this->shiftDateYear($source['due_date'] ?? null, $baseYear, $year),
            'success_criteria' => $source['success_criteria'] ?? null,
            'motivation' => $source['motivation'] ?? null,
            'next_action' => $source['next_action'] ?? null,
            'external_system' => $source['external_system'] ?? null,
            'external_url' => $source['external_url'] ?? null,
        ]);
    }

    private function shiftDateYear(mixed $date, int $fromYear, int $toYear): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        try {
            $dt = new DateTimeImmutable((string) $date);
        } catch (Throwable) {
            return null;
        }
        $delta = $toYear - $fromYear;
        if ($delta === 0) {
            return $dt->format('Y-m-d');
        }
        $month = (int) $dt->format('n');
        $day = (int) $dt->format('j');
        $newYear = (int) $dt->format('Y') + $delta;
        if (!checkdate($month, $day, $newYear)) {
            $day = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $newYear, $month)))->format('t');
        }
        return sprintf('%04d-%02d-%02d', $newYear, $month, $day);
    }
}
