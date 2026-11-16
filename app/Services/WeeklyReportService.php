<?php

declare(strict_types=1);

final class WeeklyReportService
{
    private WeeklyPlanService $plan;

    public function __construct()
    {
        $this->plan = new WeeklyPlanService();
        (new SportService())->ensureTable();
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $userId, ?DateTimeImmutable $anchor = null): array
    {
        $anchor = $anchor ?? now_local();
        $bounds = $this->plan->weekBounds($anchor);
        $monday = $bounds['monday'];
        $sunday = $bounds['sunday'];
        $startDay = $monday->format('Y-m-d');
        $endDay = $sunday->format('Y-m-d');
        [$startUtc, $endUtc] = $this->utcRange($monday, $sunday);

        $currentMonday = $this->plan->weekBounds()['monday']->format('Y-m-d');
        $isCurrentWeek = $startDay === $currentMonday;

        $months = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $rangeLabel = $monday->format('j') . ' '
            . $months[(int) $monday->format('n')]
            . ' – ' . $sunday->format('j') . ' '
            . $months[(int) $sunday->format('n')]
            . ' ' . $sunday->format('Y');

        $tasks = $this->weekTasks($userId, $startDay, $endDay);
        $training = $this->weekTraining($userId, $startDay, $endDay);
        $habits = $this->weekHabits($userId, $startDay, $endDay);
        $habitsDoneCount = 0;
        foreach ($habits as $row) {
            $habitsDoneCount += (int) ($row['times'] ?? 0);
        }
        $goals = $this->completedGoals($userId, $startUtc, $endUtc, false);
        $horizons = $this->completedGoals($userId, $startUtc, $endUtc, true);
        $milestones = $this->completedMilestones($userId, $startUtc, $endUtc);

        $tasksDone = count(array_filter($tasks, static fn (array $t): bool => !empty($t['is_done'])));

        return [
            'weekStart' => $startDay,
            'weekEnd' => $endDay,
            'prevWeek' => $monday->modify('-7 days')->format('Y-m-d'),
            'nextWeek' => $monday->modify('+7 days')->format('Y-m-d'),
            'weekNumber' => $monday->format('W'),
            'weekYear' => $monday->format('o'),
            'rangeLabel' => $rangeLabel,
            'isCurrentWeek' => $isCurrentWeek,
            'tasks' => $tasks,
            'tasksDone' => $tasksDone,
            'training' => $training,
            'trainingCount' => count($training),
            'habits' => $habits,
            'habitsDoneCount' => $habitsDoneCount,
            'goals' => $goals,
            'milestones' => $milestones,
            'horizons' => $horizons,
            'completionsCount' => count($goals) + count($milestones) + count($horizons),
        ];
    }

    /** @return array{0:string,1:string} */
    private function utcRange(DateTimeImmutable $monday, DateTimeImmutable $sunday): array
    {
        $tz = now_local()->getTimezone();
        $startUtc = (new DateTimeImmutable($monday->format('Y-m-d') . ' 00:00:00', $tz))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
        $endUtc = (new DateTimeImmutable($sunday->format('Y-m-d') . ' 00:00:00', $tz))
            ->modify('+1 day')
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        return [$startUtc, $endUtc];
    }

    /**
     * Todo el plan semanal de la semana (hechas y pendientes).
     *
     * @return list<array<string, mixed>>
     */
    private function weekTasks(int $userId, string $startDay, string $endDay): array
    {
        $this->plan->ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT id, title, task_date, task_kind, is_done, completed_at, estimated_minutes
             FROM weekly_tasks
             WHERE user_id = :uid AND deleted_at IS NULL
               AND task_date BETWEEN :a AND :b
             ORDER BY task_date ASC, sort_order ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId, 'a' => $startDay, 'b' => $endDay]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Registros de entrenamiento de la semana (disciplinas + rutina).
     *
     * @return list<array<string, mixed>>
     */
    private function weekTraining(int $userId, string $startDay, string $endDay): array
    {
        $pdo = Database::pdo();
        $rows = [];

        $discStmt = $pdo->prepare(
            'SELECT d.name, d.kind, l.log_date, l.quantity, l.attended
             FROM sport_logs l
             INNER JOIN sport_disciplines d ON d.id = l.discipline_id AND d.deleted_at IS NULL
             WHERE l.user_id = :uid AND l.log_date BETWEEN :a AND :b
             ORDER BY l.log_date ASC, d.name ASC'
        );
        $discStmt->execute(['uid' => $userId, 'a' => $startDay, 'b' => $endDay]);
        foreach ($discStmt->fetchAll() ?: [] as $row) {
            $kind = (string) ($row['kind'] ?? '');
            if ($kind === 'distance') {
                $qty = (float) ($row['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $meta = number_format($qty, 1, ',', '.') . ' km · ' . format_date((string) $row['log_date'], 'd/m');
            } else {
                if (empty($row['attended'])) {
                    continue;
                }
                $meta = 'Clase · ' . format_date((string) $row['log_date'], 'd/m');
            }
            $rows[] = [
                'title' => (string) $row['name'],
                'meta' => $meta,
                'sort' => (string) $row['log_date'],
            ];
        }

        $exStmt = $pdo->prepare(
            'SELECT e.name, l.log_date, l.reps
             FROM sport_exercise_logs l
             INNER JOIN sport_exercises e ON e.id = l.exercise_id AND e.deleted_at IS NULL
             WHERE l.user_id = :uid AND l.is_done = 1 AND l.log_date BETWEEN :a AND :b
             ORDER BY l.log_date ASC, e.sort_order ASC, e.id ASC'
        );
        $exStmt->execute(['uid' => $userId, 'a' => $startDay, 'b' => $endDay]);
        foreach ($exStmt->fetchAll() ?: [] as $row) {
            $reps = (int) ($row['reps'] ?? 0);
            $rows[] = [
                'title' => (string) $row['name'],
                'meta' => '×' . $reps . ' · ' . format_date((string) $row['log_date'], 'd/m'),
                'sort' => (string) $row['log_date'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['sort'], (string) $b['sort']));

        return $rows;
    }

    /**
     * Hábitos marcados en la semana con cantidad de repeticiones.
     *
     * @return list<array<string, mixed>>
     */
    private function weekHabits(int $userId, string $startDay, string $endDay): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT h.title,
                    COUNT(l.id) AS times,
                    COALESCE(SUM(CASE WHEN l.quantity IS NOT NULL THEN l.quantity ELSE 0 END), 0) AS qty_sum
             FROM habit_logs l
             INNER JOIN habits h ON h.id = l.habit_id AND h.user_id = l.user_id AND h.deleted_at IS NULL
             WHERE l.user_id = :uid
               AND l.log_date BETWEEN :a AND :b
               AND l.status IN (\'completed\', \'partial\')
             GROUP BY h.id, h.title
             ORDER BY times DESC, h.title ASC'
        );
        $stmt->execute(['uid' => $userId, 'a' => $startDay, 'b' => $endDay]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function completedGoals(int $userId, string $startUtc, string $endUtc, bool $horizons): array
    {
        $horizonSql = $horizons ? 'AND horizon = \'largo_plazo\'' : 'AND horizon <> \'largo_plazo\'';
        $stmt = Database::pdo()->prepare(
            "SELECT id, title, completed_at, status, progress_percent
             FROM goals
             WHERE user_id = :uid AND deleted_at IS NULL
               {$horizonSql}
               AND completed_at IS NOT NULL
               AND completed_at >= :a AND completed_at < :b
               AND (status = 'completed' OR progress_percent >= 100)
             ORDER BY completed_at ASC, title ASC"
        );
        $stmt->execute(['uid' => $userId, 'a' => $startUtc, 'b' => $endUtc]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function completedMilestones(int $userId, string $startUtc, string $endUtc): array
    {
        (new MilestoneService())->ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT id, title, milestone_date, completed_at
             FROM milestones
             WHERE user_id = :uid AND deleted_at IS NULL AND is_done = 1
               AND completed_at IS NOT NULL
               AND completed_at >= :a AND completed_at < :b
             ORDER BY completed_at ASC, title ASC'
        );
        $stmt->execute(['uid' => $userId, 'a' => $startUtc, 'b' => $endUtc]);

        return $stmt->fetchAll() ?: [];
    }
}
