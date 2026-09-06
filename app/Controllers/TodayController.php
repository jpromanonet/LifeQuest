<?php

declare(strict_types=1);

final class TodayController
{
    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $user = Auth::user();
        $now = now_local();

        $habits = new HabitService();
        $metrics = new MetricsService($habits);

        $todayHabits = $habits->todayHabits($userId);
        $overview = $metrics->overview($userId, ['year' => (int) $now->format('Y')]);
        $yearProgress = $this->yearProgress($now);

        $doneToday = 0;
        foreach ($todayHabits as $habit) {
            $status = $habit['log_status'] ?? null;
            if ($status === 'completed' || $status === 'partial') {
                $doneToday++;
            }
        }
        $habitsTotal = count($todayHabits);

        $weekLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $weeklyChart = $this->weeklyHabitStackChart($userId, $now, $habits);

        $elapsed = (float) $yearProgress['percent'];
        $remaining = max(0.0, 100.0 - $elapsed);
        $yearRingChart = [
            'type' => 'doughnut',
            'data' => [
                'labels' => ['Transcurrido', 'Restante'],
                'datasets' => [[
                    'data' => [$elapsed, $remaining],
                    'backgroundColor' => ['#4FAF8E', '#D8EEE6'],
                    'borderWidth' => 0,
                    'hoverOffset' => 2,
                ]],
            ],
            'options' => [
                'cutout' => '72%',
                'plugins' => [
                    'legend' => ['display' => false],
                    'tooltip' => ['enabled' => false],
                ],
            ],
        ];

        $yearMonthsChart = $this->yearMonthsChart($userId, (int) $now->format('Y'));

        $weekly = new WeeklyPlanService();
        $weekBoard = $weekly->weekBoard($userId, $now);
        $todayDate = $now->format('Y-m-d');
        $todayTasks = [];
        foreach ($weekBoard as $day) {
            if ((string) ($day['date'] ?? '') === $todayDate) {
                $todayTasks = $day['tasks'] ?? [];
                break;
            }
        }
        $taskDone = 0;
        foreach ($todayTasks as $task) {
            if ((int) ($task['is_done'] ?? 0) === 1) {
                $taskDone++;
            }
        }
        $taskTotal = count($todayTasks);
        $todayMins = WeeklyPlanService::minutesFromTasks($todayTasks);
        $weekMinTotal = 0;
        $weekMinDone = 0;
        foreach ($weekBoard as $day) {
            $weekMinTotal += (int) ($day['minutes_total'] ?? 0);
            $weekMinDone += (int) ($day['minutes_done'] ?? 0);
        }

        view('today/index', [
            'title' => 'Hoy',
            'currentNav' => 'today',
            'greeting' => greeting_for_hour((int) $now->format('G')),
            'firstName' => first_name((string) ($user['name'] ?? '')),
            'todayLabel' => $this->formatSpanishDate($now),
            'todayHabits' => $todayHabits,
            'overview' => $overview,
            'habitsDone' => $doneToday,
            'habitsTotal' => $habitsTotal,
            'weeklyChart' => $weeklyChart,
            'yearProgress' => $yearProgress,
            'yearRingChart' => $yearRingChart,
            'yearMonthsChart' => $yearMonthsChart,
            'todayDate' => $todayDate,
            'todayTasks' => $todayTasks,
            'weekTitleIndex' => WeeklyPlanService::titleIndexFromBoard($weekBoard),
            'todayTaskStats' => [
                'total' => $taskTotal,
                'done' => $taskDone,
                'percent' => $taskTotal > 0 ? round(($taskDone / $taskTotal) * 100, 1) : 0.0,
                'complete' => $taskTotal > 0 && $taskDone === $taskTotal,
                'minutes_total' => $todayMins['total'],
                'minutes_done' => $todayMins['done'],
            ],
            'weekHours' => [
                'minutes_total' => $weekMinTotal,
                'minutes_done' => $weekMinDone,
            ],
            'dueMilestones' => (new MilestoneService())->pendingDue($userId, $todayDate),
        ]);
    }

    /**
     * Barras apiladas: cada hábito del día aporta su color en proporción (100 / cantidad del día).
     *
     * @return array<string, mixed>
     */
    private function weeklyHabitStackChart(int $userId, DateTimeImmutable $today, HabitService $habits): array
    {
        $labels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $dow = (int) $today->format('N');
        $monday = $today->modify('-' . ($dow - 1) . ' days');

        /** @var array<int, array{name:string,color:string}> $habitMeta */
        $habitMeta = [];
        /** @var list<array<int, float>> $perDay */
        $perDay = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $monday->modify('+' . $i . ' day');
            $dayHabits = $habits->todayHabits($userId, $day->format('Y-m-d'));
            $count = count($dayHabits);
            $share = $count > 0 ? round(100 / $count, 2) : 0.0;
            $dayMap = [];
            foreach ($dayHabits as $habit) {
                $id = (int) $habit['id'];
                $habitMeta[$id] = [
                    'name' => (string) $habit['name'],
                    'color' => habit_color_hex((int) ($habit['display_number'] ?? 0)),
                ];
                $done = ($habit['log_status'] ?? null) === 'completed'
                    || ($habit['log_status'] ?? null) === 'partial';
                $dayMap[$id] = $done ? $share : 0.0;
            }
            $perDay[] = $dayMap;
        }

        $datasets = [];
        foreach ($habitMeta as $id => $meta) {
            $data = [];
            for ($i = 0; $i < 7; $i++) {
                $data[] = (float) ($perDay[$i][$id] ?? 0);
            }
            $datasets[] = [
                'label' => $meta['name'],
                'data' => $data,
                'backgroundColor' => $meta['color'],
                'stack' => 'habits',
                'borderWidth' => 0,
                'borderSkipped' => false,
                'borderRadius' => 3,
                'maxBarThickness' => 36,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Porcentaje de objetivos (modo meses) con el mes tildado, para cada mes del año.
     *
     * @return array<string, mixed>
     */
    private function yearMonthsChart(int $userId, int $year): array
    {
        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $pdo = Database::pdo();

        $totalStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM goals
             WHERE user_id = :uid
               AND deleted_at IS NULL
               AND period_year = :year
               AND horizon <> 'largo_plazo'
               AND COALESCE(progress_mode, 'months') = 'months'
               AND status NOT IN ('cancelled', 'archived')"
        );
        $totalStmt->execute(['uid' => $userId, 'year' => $year]);
        $totalGoals = max(0, (int) $totalStmt->fetchColumn());

        $checked = array_fill(1, 12, 0);
        if ($totalGoals > 0) {
            $stmt = $pdo->prepare(
                "SELECT c.month_num AS m, COUNT(*) AS n
                 FROM goal_month_checks c
                 INNER JOIN goals g ON g.id = c.goal_id
                 WHERE g.user_id = :uid
                   AND g.deleted_at IS NULL
                   AND g.period_year = :year
                   AND g.horizon <> 'largo_plazo'
                   AND COALESCE(g.progress_mode, 'months') = 'months'
                   AND g.status NOT IN ('cancelled', 'archived')
                   AND c.is_checked = 1
                   AND c.month_num BETWEEN 1 AND 12
                 GROUP BY c.month_num"
            );
            $stmt->execute(['uid' => $userId, 'year' => $year]);
            foreach ($stmt->fetchAll() as $row) {
                $m = (int) $row['m'];
                if ($m >= 1 && $m <= 12) {
                    $checked[$m] = (int) $row['n'];
                }
            }
        }

        $data = [];
        for ($m = 1; $m <= 12; $m++) {
            $data[] = $totalGoals > 0
                ? round(($checked[$m] / $totalGoals) * 100, 1)
                : 0.0;
        }

        $colors = [];
        for ($m = 1; $m <= 12; $m++) {
            $t = $data[$m - 1] / 100;
            $colors[] = $this->greenShade($t);
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Avance %',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderRadius' => 6,
                    'maxBarThickness' => 28,
                ]],
            ],
            'options' => [
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'max' => 100,
                    ],
                    'x' => [
                        'grid' => ['display' => false],
                    ],
                ],
                'plugins' => [
                    'legend' => ['display' => false],
                ],
            ],
        ];
    }

    private function greenShade(float $t): string
    {
        $t = max(0.0, min(1.0, $t));
        // De mint suave a verde más intenso según el %
        $r = (int) round(209 - (209 - 79) * $t);
        $g = (int) round(232 - (232 - 175) * $t);
        $b = (int) round(220 - (220 - 142) * $t);
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /**
     * @return array{
     *   year:int,percent:float,days_elapsed:int,days_remaining:int,
     *   days_total:int,weeks_elapsed:int,weeks_remaining:int,weeks_total:int
     * }
     */
    private function yearProgress(DateTimeImmutable $now): array
    {
        $year = (int) $now->format('Y');
        $yearEnd = new DateTimeImmutable($year . '-12-31', $now->getTimezone());
        $daysTotal = (int) $yearEnd->format('z') + 1;
        $daysElapsed = (int) $now->format('z') + 1;
        $daysRemaining = max(0, $daysTotal - $daysElapsed);
        $weeksTotal = (int) ceil($daysTotal / 7);
        $weeksElapsed = (int) floor(($daysElapsed - 1) / 7);
        $weeksRemaining = max(0, $weeksTotal - $weeksElapsed);
        $percent = $daysTotal > 0 ? round(($daysElapsed / $daysTotal) * 100, 1) : 0.0;

        return [
            'year' => $year,
            'percent' => $percent,
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'days_total' => $daysTotal,
            'weeks_elapsed' => $weeksElapsed,
            'weeks_remaining' => $weeksRemaining,
            'weeks_total' => $weeksTotal,
        ];
    }

    private function formatSpanishDate(DateTimeImmutable $dt): string
    {
        $days = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
            5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
        ];
        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];
        $dow = (int) $dt->format('N');
        $day = (int) $dt->format('j');
        $month = (int) $dt->format('n');

        return $days[$dow] . ', ' . $day . ' de ' . $months[$month];
    }

    /** @return array{type:string,label:string,date_label:string,hint:string} */
    private function nextReviewSuggestion(DateTimeImmutable $now): array
    {
        $dow = (int) $now->format('N');
        $daysUntilSunday = $dow === 7 ? 0 : (7 - $dow);
        $nextSunday = $now->modify('+' . $daysUntilSunday . ' days');

        $days = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
            5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
        ];
        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        $dateLabel = $days[(int) $nextSunday->format('N')]
            . ', ' . (int) $nextSunday->format('j')
            . ' de ' . $months[(int) $nextSunday->format('n')];

        return [
            'type' => 'weekly',
            'label' => 'Revisión semanal',
            'date_label' => $dateLabel,
            'hint' => '10:00 · ~45 min',
        ];
    }
}
