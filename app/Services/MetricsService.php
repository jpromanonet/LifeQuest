<?php

declare(strict_types=1);

final class MetricsService
{
    private const MONTH_LABELS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private HabitService $habits;
    private GoalService $goals;

    public function __construct(?HabitService $habits = null, ?GoalService $goals = null)
    {
        $this->habits = $habits ?? new HabitService();
        $this->goals = $goals ?? new GoalService();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   goals_total: int,
     *   goals_completed: int,
     *   goals_active: int,
     *   goals_at_risk: int,
     *   completion_rate: float,
     *   avg_progress: float,
     *   habits_compliance_today: float,
     *   current_streak_best: int,
     *   weekly_progress: list<float>,
     *   area_distribution: list<array{area_id: ?int, area_name: string, area_color: ?string, count: int}>,
     *   habit_heatmap: list<array{date: string, value: float}>
     * }
     */
    public function overview(int $userId, array $filters = []): array
    {
        $year = isset($filters['year']) && $filters['year'] !== '' && $filters['year'] !== null
            ? (int) $filters['year']
            : (int) now_local()->format('Y');
        $areaId = !empty($filters['area_id']) ? (int) $filters['area_id'] : null;

        $goalStats = $this->goalStats($userId, $year, $areaId);
        $atRisk = $this->countAtRisk($userId, $year, $areaId);

        $today = now_local();
        $todayHabits = $this->habits->todayHabits($userId, $today->format('Y-m-d'));
        $habitsComplianceToday = $this->complianceRatio($todayHabits);

        $activeHabits = $this->habits->listActive($userId);
        $bestCurrentStreak = 0;
        foreach ($activeHabits as $habit) {
            $streak = $this->habits->streak((int) $habit['id']);
            $bestCurrentStreak = max($bestCurrentStreak, (int) $streak['current_streak']);
        }

        $compareYear = $year - 1;
        if (array_key_exists('compare_year', $filters) && $filters['compare_year'] !== null && $filters['compare_year'] !== '') {
            $compareYear = (int) $filters['compare_year'];
        }
        if ($compareYear === $year || $compareYear < 2000 || $compareYear > 2100) {
            $compareYear = $year - 1;
        }

        return [
            'goals_total' => $goalStats['total'],
            'goals_completed' => $goalStats['completed'],
            'goals_active' => $goalStats['active'],
            'goals_at_risk' => $atRisk,
            'completion_rate' => $goalStats['completion_rate'],
            'avg_progress' => $goalStats['avg_progress'],
            'habits_compliance_today' => $habitsComplianceToday,
            'current_streak_best' => $bestCurrentStreak,
            'weekly_progress' => $this->weeklyProgress($userId, $today),
            'area_distribution' => $this->areaDistribution($userId, $year),
            'habit_heatmap' => $this->habitHeatmap($userId, $today, 12),
            'areas' => $this->areaSnapshot($userId, $year),
            'books' => $this->bookStats($userId, $year),
            'prev_year' => $this->areaSnapshot($userId, $compareYear),
            'compare_year' => $compareYear,
            'monthly_completion' => $this->monthlyCompletionBar($userId, $year),
            'weekly_plan' => (new WeeklyPlanService())->metricsSnapshot($userId),
            'milestones' => (new MilestoneService())->stats($userId),
            'water' => $this->habits->waterStats($userId, $today),
            'fruit' => $this->habits->fruitStats($userId, $today),
            'friends' => (new FriendService())->stats($userId),
            'catalog' => MetricCatalog::byArea(),
            'consolidated_catalog' => MetricCatalog::consolidated(),
            'catalog_metrics' => $this->catalogMetrics($userId, $year, $today),
        ];
    }

    /**
     * Métricas del catálogo con valores calculados desde datos reales.
     *
     * @return array{
     *   consolidated: list<array{label:string,value:string,hint?:string}>,
     *   areas: array<string, array{name:string,day:list, year:list, years:list}>
     * }
     */
    public function catalogMetrics(int $userId, int $year, ?DateTimeImmutable $today = null): array
    {
        $today = $today ?? now_local();
        $snap = $this->areaSnapshot($userId, $year);
        $prevSnap = $this->areaSnapshot($userId, $year - 1);
        $prevByKey = [];
        foreach ($prevSnap as $row) {
            $prevByKey[$row['area_key']] = $row;
        }

        $goalStats = $this->goalStats($userId, $year, null);
        $atRisk = $this->countAtRisk($userId, $year, null);
        $todayHabits = $this->habits->todayHabits($userId, $today->format('Y-m-d'));
        $habitsToday = $this->complianceRatio($todayHabits);
        $activeHabits = count($this->habits->listActive($userId));
        $books = $this->bookStats($userId, $year);
        $activeDays = $this->activeHabitDays($userId, $year);
        $bestStreak = 0;
        foreach ($this->habits->listActive($userId) as $habit) {
            $streak = $this->habits->streak((int) $habit['id']);
            $bestStreak = max($bestStreak, (int) $streak['current_streak']);
        }

        $activeAreas = 0;
        $areaCount = count($snap);
        $maxArea = ['name' => '—', 'avg' => 0.0];
        $minArea = ['name' => '—', 'avg' => 100.0];
        foreach ($snap as $a) {
            if ($a['total'] > 0) {
                $activeAreas++;
            }
            if ($a['avg_progress'] >= $maxArea['avg']) {
                $maxArea = ['name' => (string) $a['name'], 'avg' => (float) $a['avg_progress']];
            }
            if ($a['total'] > 0 && $a['avg_progress'] <= $minArea['avg']) {
                $minArea = ['name' => (string) $a['name'], 'avg' => (float) $a['avg_progress']];
            }
        }

        $expected = $this->expectedYearProgress($year);
        $monthsAll = $this->monthCheckStats($userId, $year, null);
        $unitsAll = $this->quantityStats($userId, $year, null);
        $yearDays = (int) (new DateTimeImmutable($year . '-12-31'))->format('z') + 1;

        $consolidated = [
            [
                'label' => 'Actividad del día (hábitos)',
                'value' => number_format($habitsToday, 0) . '%',
                'hint' => count($todayHabits) . ' programados hoy',
                'percent' => $habitsToday,
            ],
            [
                'label' => 'Progreso anual promedio',
                'value' => number_format($goalStats['avg_progress'], 0) . '%',
                'hint' => 'Esperado a la fecha: ' . number_format($expected, 0) . '%',
                'percent' => $goalStats['avg_progress'],
            ],
            [
                'label' => 'Completitud anual',
                'value' => number_format($goalStats['completion_rate'], 0) . '%',
                'hint' => $goalStats['completed'] . '/' . $goalStats['total'] . ' objetivos',
                'percent' => $goalStats['completion_rate'],
            ],
            [
                'label' => 'Objetivos activos',
                'value' => (string) $goalStats['active'],
                'hint' => $goalStats['completed'] . ' completados · ' . $goalStats['total'] . ' totales',
                'percent' => $goalStats['total'] > 0 ? ($goalStats['active'] / $goalStats['total']) * 100 : 0.0,
            ],
            [
                'label' => 'Objetivos en riesgo',
                'value' => (string) $atRisk,
                'hint' => 'Activos atrasados o vencidos',
                'percent' => $goalStats['total'] > 0 ? ($atRisk / $goalStats['total']) * 100 : 0.0,
                'tone' => 'warn',
            ],
            [
                'label' => 'Meses tildados',
                'value' => $monthsAll['checked'] . '/' . $monthsAll['possible'],
                'hint' => 'Objetivos medidos por meses',
                'percent' => $monthsAll['percent'],
            ],
            [
                'label' => 'Unidades cargadas',
                'value' => number_format($unitsAll['current'], 0) . '/' . number_format($unitsAll['target'], 0),
                'hint' => 'Objetivos medidos por unidades',
                'percent' => $unitsAll['percent'],
            ],
            [
                'label' => 'Variación vs ' . ($year - 1),
                'value' => $this->yearDeltaLabel($snap, $prevByKey),
                'hint' => 'Promedio por área',
            ],
            [
                'label' => 'Días con actividad de hábitos',
                'value' => $activeDays . '/' . $yearDays,
                'hint' => 'Racha máxima ' . $bestStreak . ' días',
                'percent' => $yearDays > 0 ? ($activeDays / $yearDays) * 100 : 0.0,
            ],
            [
                'label' => 'Áreas activas',
                'value' => $activeAreas . '/' . max(1, $areaCount),
                'hint' => 'Con objetivos cargados',
                'percent' => $areaCount > 0 ? ($activeAreas / $areaCount) * 100 : 0.0,
            ],
            [
                'label' => 'Área con mayor progreso',
                'value' => $maxArea['name'],
                'hint' => number_format($maxArea['avg'], 0) . '%',
                'percent' => $maxArea['avg'],
            ],
            [
                'label' => 'Área con menor progreso',
                'value' => $minArea['name'],
                'hint' => number_format($minArea['avg'], 0) . '%',
                'percent' => $minArea['avg'],
                'tone' => 'warn',
            ],
            [
                'label' => 'Hábitos activos',
                'value' => (string) $activeHabits,
                'hint' => 'En seguimiento',
            ],
            [
                'label' => 'Libros terminados',
                'value' => $books['finished'] . '/' . $books['target'],
                'hint' => $books['pages'] . ' páginas · ' . $books['reading'] . ' leyendo',
                'percent' => min(100, ($books['finished'] / max(1, $books['target'])) * 100),
            ],
        ];

        $areas = [];
        foreach ($snap as $a) {
            $key = (string) $a['area_key'];
            $areaId = $a['area_id'] !== null ? (int) $a['area_id'] : null;
            $prev = $prevByKey[$key] ?? null;
            $delta = $prev ? ((float) $a['avg_progress'] - (float) $prev['avg_progress']) : null;
            $pipeline = $this->areaPipeline($userId, $year, $areaId);
            $months = $this->monthCheckStats($userId, $year, $areaId);
            $units = $this->quantityStats($userId, $year, $areaId);
            $nextDue = $this->areaNextDue($userId, $year, $areaId);

            $day = [
                [
                    'label' => 'Objetivos activos',
                    'value' => (string) $a['active'],
                    'hint' => $pipeline['planned'] . ' planificados · ' . $pipeline['paused'] . ' pausados',
                    'percent' => $a['total'] > 0 ? ($a['active'] / $a['total']) * 100 : 0.0,
                ],
                [
                    'label' => 'En riesgo',
                    'value' => (string) $pipeline['at_risk'],
                    'hint' => $pipeline['overdue'] . ' vencidos',
                    'percent' => $a['total'] > 0 ? ($pipeline['at_risk'] / $a['total']) * 100 : 0.0,
                    'tone' => 'warn',
                ],
                [
                    'label' => 'Próximo vencimiento',
                    'value' => $nextDue['label'],
                    'hint' => $nextDue['title'],
                ],
            ];

            $yearMetrics = [
                [
                    'label' => 'Progreso promedio',
                    'value' => number_format((float) $a['avg_progress'], 0) . '%',
                    'hint' => 'Esperado a la fecha: ' . number_format($expected, 0) . '%',
                    'percent' => (float) $a['avg_progress'],
                ],
                [
                    'label' => 'Completados',
                    'value' => $a['completed'] . '/' . $a['total'],
                    'hint' => number_format((float) $a['completion_rate'], 0) . '% del área',
                    'percent' => (float) $a['completion_rate'],
                ],
                [
                    'label' => 'Activos',
                    'value' => (string) $a['active'],
                    'hint' => $pipeline['planned'] . ' planificados · ' . $pipeline['paused'] . ' pausados',
                    'percent' => $a['total'] > 0 ? ($a['active'] / $a['total']) * 100 : 0.0,
                ],
                [
                    'label' => 'En riesgo',
                    'value' => (string) $pipeline['at_risk'],
                    'hint' => $pipeline['overdue'] . ' vencidos',
                    'percent' => $a['total'] > 0 ? ($pipeline['at_risk'] / $a['total']) * 100 : 0.0,
                    'tone' => 'warn',
                ],
                [
                    'label' => 'Meses tildados',
                    'value' => $months['checked'] . '/' . $months['possible'],
                    'hint' => $months['goals'] . ' objetivos por meses',
                    'percent' => $months['percent'],
                ],
                [
                    'label' => 'Unidades cargadas',
                    'value' => number_format($units['current'], 0) . '/' . number_format($units['target'], 0),
                    'hint' => $units['goals'] . ' objetivos por unidades',
                    'percent' => $units['percent'],
                ],
                [
                    'label' => 'Sí / no cerrados',
                    'value' => $pipeline['binary_done'] . '/' . $pipeline['binary'],
                    'hint' => 'Objetivos de resultado único',
                    'percent' => $pipeline['binary'] > 0 ? ($pipeline['binary_done'] / $pipeline['binary']) * 100 : 0.0,
                ],
                [
                    'label' => 'Próximo vencimiento',
                    'value' => $nextDue['label'],
                    'hint' => $nextDue['title'],
                ],
                [
                    'label' => 'Comparación vs año anterior',
                    'value' => $delta === null ? '—' : (($delta >= 0 ? '+' : '') . number_format($delta, 0) . ' pts'),
                    'hint' => 'Progreso promedio del área',
                ],
                [
                    'label' => 'Histórico del área',
                    'value' => (string) $this->areaGoalTotalAllYears($userId, $key),
                    'hint' => 'Mejor año: ' . $this->bestYearForArea($userId, $key),
                ],
            ];

            if ($key === 'annual_educacion') {
                $yearMetrics[] = [
                    'label' => 'Libros terminados',
                    'value' => $books['finished'] . '/' . $books['target'],
                    'hint' => 'Meta del año: ' . $books['target'] . ' libros',
                    'percent' => min(100, ($books['finished'] / max(1, $books['target'])) * 100),
                ];
                $yearMetrics[] = [
                    'label' => 'Páginas leídas',
                    'value' => number_format($books['pages'], 0),
                    'hint' => $books['reading'] . ' en curso · ' . $books['planned'] . ' planeados',
                ];
            }
            if ($key === 'annual_proyectos') {
                $projects = $this->projectStats($userId, $year);
                $yearMetrics[] = [
                    'label' => 'Proyectos completados',
                    'value' => $projects['completed'] . '/' . $projects['total'],
                    'hint' => $projects['active'] . ' activos',
                    'percent' => $projects['total'] > 0 ? ($projects['completed'] / $projects['total']) * 100 : 0.0,
                ];
            }

            $areas[$key] = [
                'name' => (string) $a['name'],
                'color' => $a['color'],
                'day' => $day,
                'year' => $yearMetrics,
                'charts' => $this->areaCharts($userId, $year, $areaId, (string) ($a['color'] ?? '')),
            ];
        }

        return [
            'consolidated' => $consolidated,
            'areas' => $areas,
        ];
    }

    /**
     * Tres gráficos por área listos para Chart.js: barras de actividad mensual,
     * torta de composición del portafolio y línea de ritmo acumulado.
     *
     * @return array{has_data:bool,bar:array<string,mixed>,pie:array<string,mixed>,line:array<string,mixed>}
     */
    private function areaCharts(int $userId, int $year, ?int $areaId, string $color): array
    {
        $checksByMonth = $this->monthlySeries(
            'SELECT c.month_num AS m, COUNT(*) AS n
             FROM goal_month_checks c
             INNER JOIN goals g ON g.id = c.goal_id
             WHERE g.user_id = :uid AND g.deleted_at IS NULL
               AND g.period_year = :year AND g.horizon <> \'largo_plazo\'
               AND c.is_checked = 1',
            ' AND g.area_id = :area_id',
            ' GROUP BY c.month_num',
            $userId,
            $year,
            $areaId
        );

        $composition = $this->statusComposition($userId, $year, $areaId);

        $hasData = array_sum($checksByMonth) > 0
            || array_sum(array_column($composition, 'value')) > 0;

        $accent = $this->normalizeHex($color);

        return [
            'has_data' => $hasData,
            'bar' => [
                'type' => 'bar',
                'data' => [
                    'labels' => self::MONTH_LABELS,
                    'datasets' => [
                        [
                            'label' => 'Meses tildados',
                            'data' => array_values($checksByMonth),
                            'backgroundColor' => $accent,
                            'borderRadius' => 6,
                        ],
                    ],
                ],
                'options' => [
                    'plugins' => ['legend' => ['position' => 'bottom']],
                    'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
                ],
            ],
            'pie' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => array_column($composition, 'label'),
                    'datasets' => [[
                        'data' => array_column($composition, 'value'),
                        'backgroundColor' => array_column($composition, 'color'),
                        'borderWidth' => 0,
                    ]],
                ],
                'options' => [
                    'cutout' => '58%',
                    'plugins' => ['legend' => ['position' => 'bottom']],
                ],
            ],
            'line' => [
                'type' => 'line',
                'data' => [
                    'labels' => self::MONTH_LABELS,
                    'datasets' => [
                        [
                            'label' => 'Meses tildados (acum.)',
                            'data' => $this->cumulative($checksByMonth),
                            'borderColor' => $accent,
                            'backgroundColor' => $this->fadeHex($accent, 0.18),
                            'fill' => true,
                            'tension' => 0.35,
                            'pointRadius' => 2,
                        ],
                    ],
                ],
                'options' => [
                    'plugins' => ['legend' => ['position' => 'bottom']],
                    'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
                ],
            ],
        ];
    }

    /**
     * Ejecuta una consulta que devuelve pares (mes, cantidad) y la expande a los 12 meses.
     *
     * @return array<int, int>
     */
    private function monthlySeries(
        string $sql,
        string $areaClause,
        string $groupBy,
        int $userId,
        int $year,
        ?int $areaId
    ): array {
        $params = ['uid' => $userId, 'year' => $year];
        if ($areaId !== null) {
            $sql .= $areaClause;
            $params['area_id'] = $areaId;
        }
        $stmt = Database::pdo()->prepare($sql . $groupBy);
        $stmt->execute($params);

        $series = array_fill(1, 12, 0);
        foreach ($stmt->fetchAll() as $row) {
            $month = (int) $row['m'];
            if ($month >= 1 && $month <= 12) {
                $series[$month] = (int) $row['n'];
            }
        }
        return $series;
    }

    /**
     * @param array<int, int> $series
     * @return list<int>
     */
    private function cumulative(array $series): array
    {
        $total = 0;
        $out = [];
        foreach ($series as $value) {
            $total += $value;
            $out[] = $total;
        }
        return $out;
    }

    /** @return list<array{label:string,value:int,color:string}> */
    private function statusComposition(int $userId, int $year, ?int $areaId): array
    {
        $sql = 'SELECT status, COUNT(*) AS n FROM goals
                WHERE user_id = :uid AND deleted_at IS NULL
                  AND period_year = :year AND horizon <> \'largo_plazo\'';
        $params = ['uid' => $userId, 'year' => $year];
        if ($areaId !== null) {
            $sql .= ' AND area_id = :area_id';
            $params['area_id'] = $areaId;
        }
        $stmt = Database::pdo()->prepare($sql . ' GROUP BY status');
        $stmt->execute($params);

        $buckets = [
            'completed' => ['label' => 'Completados', 'value' => 0, 'color' => '#78C6B0'],
            'active' => ['label' => 'Activos', 'value' => 0, 'color' => '#7C83E1'],
            'planned' => ['label' => 'Planificados', 'value' => 0, 'color' => '#C9B6E4'],
            'paused' => ['label' => 'Pausados', 'value' => 0, 'color' => '#F6D58A'],
            'cancelled' => ['label' => 'Cancelados', 'value' => 0, 'color' => '#F4B8A8'],
        ];
        $map = [
            'completed' => 'completed',
            'active' => 'active',
            'idea' => 'planned',
            'planned' => 'planned',
            'paused' => 'paused',
            'cancelled' => 'cancelled',
            'archived' => 'cancelled',
        ];

        foreach ($stmt->fetchAll() as $row) {
            $bucket = $map[(string) $row['status']] ?? 'planned';
            $buckets[$bucket]['value'] += (int) $row['n'];
        }

        return array_values(array_filter($buckets, static fn (array $b): bool => $b['value'] > 0));
    }

    private function normalizeHex(?string $color): string
    {
        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#7C83E1';
    }

    private function fadeHex(string $hex, float $alpha): string
    {
        return sprintf(
            'rgba(%d, %d, %d, %s)',
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
            rtrim(rtrim(number_format($alpha, 2, '.', ''), '0'), '.')
        );
    }

    /** @return array{checked:int,possible:int,goals:int,percent:float} */
    private function monthCheckStats(int $userId, int $year, ?int $areaId): array
    {
        $sql = 'SELECT COUNT(DISTINCT g.id) AS goals,
                       COALESCE(SUM(CASE WHEN c.is_checked = 1 THEN 1 ELSE 0 END), 0) AS checked
                FROM goals g
                LEFT JOIN goal_month_checks c ON c.goal_id = g.id
                WHERE g.user_id = :uid AND g.deleted_at IS NULL
                  AND g.period_year = :year AND g.progress_mode = \'months\'
                  AND g.horizon <> \'largo_plazo\'';
        $params = ['uid' => $userId, 'year' => $year];
        if ($areaId !== null) {
            $sql .= ' AND g.area_id = :area_id';
            $params['area_id'] = $areaId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        $goals = (int) ($row['goals'] ?? 0);
        $checked = (int) ($row['checked'] ?? 0);
        $possible = $goals * 12;
        return [
            'goals' => $goals,
            'checked' => $checked,
            'possible' => $possible,
            'percent' => $possible > 0 ? round(($checked / $possible) * 100, 2) : 0.0,
        ];
    }

    /** @return array{current:float,target:float,goals:int,percent:float} */
    private function quantityStats(int $userId, int $year, ?int $areaId): array
    {
        $sql = 'SELECT COUNT(*) AS goals,
                       COALESCE(SUM(current_value), 0) AS current_total,
                       COALESCE(SUM(target_value), 0) AS target_total
                FROM goals
                WHERE user_id = :uid AND deleted_at IS NULL
                  AND period_year = :year AND progress_mode = \'quantity\'
                  AND horizon <> \'largo_plazo\'';
        $params = ['uid' => $userId, 'year' => $year];
        if ($areaId !== null) {
            $sql .= ' AND area_id = :area_id';
            $params['area_id'] = $areaId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        $current = (float) ($row['current_total'] ?? 0);
        $target = (float) ($row['target_total'] ?? 0);
        return [
            'goals' => (int) ($row['goals'] ?? 0),
            'current' => $current,
            'target' => $target,
            'percent' => $target > 0 ? round(min(100, ($current / $target) * 100), 2) : 0.0,
        ];
    }

    /** @return array{planned:int,paused:int,overdue:int,at_risk:int,binary:int,binary_done:int} */
    private function areaPipeline(int $userId, int $year, ?int $areaId): array
    {
        $sql = 'SELECT
                    SUM(CASE WHEN status IN (\'idea\', \'planned\') THEN 1 ELSE 0 END) AS planned,
                    SUM(CASE WHEN status = \'paused\' THEN 1 ELSE 0 END) AS paused,
                    SUM(CASE WHEN status = \'active\' AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue,
                    SUM(CASE WHEN progress_mode = \'binary\' THEN 1 ELSE 0 END) AS binary_total,
                    SUM(CASE WHEN progress_mode = \'binary\' AND (status = \'completed\' OR progress_percent >= 100) THEN 1 ELSE 0 END) AS binary_done
                FROM goals
                WHERE user_id = :uid AND deleted_at IS NULL
                  AND period_year = :year AND horizon <> \'largo_plazo\'';
        $params = ['uid' => $userId, 'year' => $year];
        if ($areaId !== null) {
            $sql .= ' AND area_id = :area_id';
            $params['area_id'] = $areaId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'planned' => (int) ($row['planned'] ?? 0),
            'paused' => (int) ($row['paused'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'at_risk' => $this->countAtRisk($userId, $year, $areaId),
            'binary' => (int) ($row['binary_total'] ?? 0),
            'binary_done' => (int) ($row['binary_done'] ?? 0),
        ];
    }

    /** @return array{label:string,title:string} */
    private function areaNextDue(int $userId, int $year, ?int $areaId): array
    {
        $sql = 'SELECT title, due_date FROM goals
                WHERE user_id = :uid AND deleted_at IS NULL
                  AND period_year = :year AND horizon <> \'largo_plazo\'
                  AND status = \'active\' AND due_date IS NOT NULL';
        $params = ['uid' => $userId, 'year' => $year];
        if ($areaId !== null) {
            $sql .= ' AND area_id = :area_id';
            $params['area_id'] = $areaId;
        }
        $sql .= ' ORDER BY due_date ASC LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!$row) {
            return ['label' => '—', 'title' => 'Sin vencimientos activos'];
        }
        return [
            'label' => format_date((string) $row['due_date'], 'd M Y'),
            'title' => (string) $row['title'],
        ];
    }

    private function yearDeltaLabel(array $snap, array $prevByKey): string
    {
        $deltas = [];
        foreach ($snap as $a) {
            $prev = $prevByKey[$a['area_key']] ?? null;
            if ($prev && $a['total'] > 0) {
                $deltas[] = (float) $a['avg_progress'] - (float) $prev['avg_progress'];
            }
        }
        if ($deltas === []) {
            return '—';
        }
        $avg = array_sum($deltas) / count($deltas);
        return ($avg >= 0 ? '+' : '') . number_format($avg, 0) . ' pts';
    }

    private function activeHabitDays(int $userId, int $year): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(DISTINCT hl.log_date) AS c
             FROM habit_logs hl
             INNER JOIN habits h ON h.id = hl.habit_id
             WHERE h.user_id = :uid
               AND h.deleted_at IS NULL
               AND hl.status IN (\'completed\', \'partial\')
               AND YEAR(hl.log_date) = :year'
        );
        $stmt->execute(['uid' => $userId, 'year' => $year]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function areaGoalTotalAllYears(int $userId, string $areaKey): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM goals g
             INNER JOIN life_areas la ON la.id = g.area_id AND la.user_id = g.user_id
             WHERE g.user_id = :uid AND g.deleted_at IS NULL
               AND la.area_key = :key AND g.horizon <> \'largo_plazo\''
        );
        $stmt->execute(['uid' => $userId, 'key' => $areaKey]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function bestYearForArea(int $userId, string $areaKey): string
    {
        $stmt = Database::pdo()->prepare(
            'SELECT g.period_year, AVG(g.progress_percent) AS avg_p
             FROM goals g
             INNER JOIN life_areas la ON la.id = g.area_id AND la.user_id = g.user_id
             WHERE g.user_id = :uid AND g.deleted_at IS NULL
               AND la.area_key = :key AND g.horizon <> \'largo_plazo\'
               AND g.period_year IS NOT NULL
             GROUP BY g.period_year
             ORDER BY avg_p DESC
             LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'key' => $areaKey]);
        $row = $stmt->fetch();
        if (!$row) {
            return '—';
        }
        return (string) $row['period_year'] . ' (' . number_format((float) $row['avg_p'], 0) . '%)';
    }

    /** @return array{active:int,completed:int,total:int} */
    private function projectStats(int $userId, int $year): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) AS active
             FROM goals
             WHERE user_id = :uid AND deleted_at IS NULL
               AND period_year = :year AND goal_type = \'project\''
        );
        $stmt->execute(['uid' => $userId, 'year' => $year]);
        $row = $stmt->fetch() ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
        ];
    }

    /**
     * Barra de completitud por mes: ancho ∝ días del mes; fill = % de objetivos con ese mes tildado.
     *
     * @return list<array{
     *   month:int,label:string,days:int,percent:float,checked:int,total:int,color:string
     * }>
     */
    public function monthlyCompletionBar(int $userId, int $year): array
    {
        $labels = self::MONTH_LABELS;
        $colors = [
            1 => '#5BA88F',
            2 => '#6BB5A0',
            3 => '#78C6B0',
            4 => '#8ACF9A',
            5 => '#A3D977',
            6 => '#78C6B0',
            7 => '#5FB8C9',
            8 => '#6BA8D4',
            9 => '#7C83E1',
            10 => '#9B8AD4',
            11 => '#C9B6E4',
            12 => '#78C6B0',
        ];

        $pdo = Database::pdo();
        $totalStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM goals
             WHERE user_id = :uid
               AND deleted_at IS NULL
               AND period_year = :year
               AND (horizon IS NULL OR horizon <> 'largo_plazo')
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
                   AND (g.horizon IS NULL OR g.horizon <> 'largo_plazo')
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

        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $days = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $m)))->format('t');
            $pct = $totalGoals > 0 ? round(($checked[$m] / $totalGoals) * 100, 1) : 0.0;
            $out[] = [
                'month' => $m,
                'label' => $labels[$m - 1],
                'days' => $days,
                'percent' => $pct,
                'checked' => $checked[$m],
                'total' => $totalGoals,
                'color' => $colors[$m],
            ];
        }
        return $out;
    }

    /**
     * Snapshot de todas las áreas anuales del usuario (las de configuración).
     *
     * @return list<array{
     *   area_key:string,area_id:?int,name:string,color:?string,
     *   total:int,active:int,completed:int,avg_progress:float,completion_rate:float
     * }>
     */
    public function areaSnapshot(int $userId, int $year): array
    {
        $areas = (new AreaService())->listByScope($userId, 'annual');
        $out = [];
        foreach ($areas as $area) {
            $areaId = (int) $area['id'];
            $stats = $this->goalStats($userId, $year, $areaId);
            $out[] = [
                'area_key' => (string) $area['area_key'],
                'area_id' => $areaId,
                'name' => (string) $area['name'],
                'color' => isset($area['color']) ? (string) $area['color'] : null,
                'total' => $stats['total'],
                'active' => $stats['active'],
                'completed' => $stats['completed'],
                'avg_progress' => $stats['avg_progress'],
                'completion_rate' => $stats['completion_rate'],
            ];
        }
        return $out;
    }

    /** @deprecated Usar areaSnapshot(); se mantiene por compatibilidad. */
    public function sixAreaSnapshot(int $userId, int $year): array
    {
        return $this->areaSnapshot($userId, $year);
    }

    /** @return array{finished:int,planned:int,reading:int,pages:int,target:int} */
    private function bookStats(int $userId, int $year): array
    {
        $target = (new BookService())->annualTarget($userId, $year);
        $exists = Database::pdo()->query("SHOW TABLES LIKE 'books'")->fetch();
        if (!$exists) {
            return ['finished' => 0, 'planned' => 0, 'reading' => 0, 'pages' => 0, 'target' => $target];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT
                SUM(CASE WHEN status = \'finished\' THEN 1 ELSE 0 END) AS finished,
                SUM(CASE WHEN status = \'planned\' THEN 1 ELSE 0 END) AS planned,
                SUM(CASE WHEN status = \'reading\' THEN 1 ELSE 0 END) AS reading,
                COALESCE(SUM(CASE WHEN status = \'finished\' THEN COALESCE(pages, pages_read, 0) ELSE pages_read END), 0) AS pages
             FROM books
             WHERE user_id = :uid AND year_num = :year AND deleted_at IS NULL'
        );
        $stmt->execute(['uid' => $userId, 'year' => $year]);
        $row = $stmt->fetch() ?: [];
        return [
            'finished' => (int) ($row['finished'] ?? 0),
            'planned' => (int) ($row['planned'] ?? 0),
            'reading' => (int) ($row['reading'] ?? 0),
            'pages' => (int) ($row['pages'] ?? 0),
            'target' => $target,
        ];
    }

    /**
     * @return array{total:int, completed:int, active:int, completion_rate:float, avg_progress:float}
     */
    private function goalStats(int $userId, int $year, ?int $areaId): array
    {
        $sql = 'SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) AS active,
                    COALESCE(AVG(progress_percent), 0) AS avg_progress
                FROM goals
                WHERE user_id = :user_id
                  AND deleted_at IS NULL
                  AND period_year = :year
                  AND horizon <> \'largo_plazo\'';
        $params = ['user_id' => $userId, 'year' => $year];
        if ($areaId !== null) {
            $sql .= ' AND area_id = :area_id';
            $params['area_id'] = $areaId;
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: ['total' => 0, 'completed' => 0, 'active' => 0, 'avg_progress' => 0];

        $total = (int) $row['total'];
        $completed = (int) $row['completed'];
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

        return [
            'total' => $total,
            'completed' => $completed,
            'active' => (int) $row['active'],
            'completion_rate' => $completionRate,
            'avg_progress' => round((float) $row['avg_progress'], 2),
        ];
    }

    private function countAtRisk(int $userId, int $year, ?int $areaId): int
    {
        $sql = 'SELECT id, due_date, progress_percent, start_date, period_year
                FROM goals
                WHERE user_id = :user_id
                  AND deleted_at IS NULL
                  AND status = \'active\'
                  AND period_year = :year
                  AND horizon <> \'largo_plazo\'';
        $params = ['user_id' => $userId, 'year' => $year];
        if ($areaId !== null) {
            $sql .= ' AND area_id = :area_id';
            $params['area_id'] = $areaId;
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $today = now_local()->format('Y-m-d');
        $expected = $this->expectedYearProgress($year);
        $count = 0;

        foreach ($stmt->fetchAll() as $goal) {
            $duePast = !empty($goal['due_date']) && $goal['due_date'] < $today;
            $behind = (float) $goal['progress_percent'] < $expected;
            if ($duePast || $behind) {
                $count++;
            }
        }

        return $count;
    }

    private function expectedYearProgress(int $year): float
    {
        $now = now_local();
        if ((int) $now->format('Y') !== $year) {
            return (int) $now->format('Y') > $year ? 100.0 : 0.0;
        }
        $start = new DateTimeImmutable($year . '-01-01', $now->getTimezone());
        $end = new DateTimeImmutable($year . '-12-31', $now->getTimezone());
        $totalDays = (int) $start->diff($end)->days + 1;
        $elapsed = (int) $start->diff($now)->days + 1;
        return round(($elapsed / max(1, $totalDays)) * 100, 2);
    }

    /** @return list<float> Monday..Sunday percentages */
    private function weeklyProgress(int $userId, DateTimeImmutable $today): array
    {
        $dow = (int) $today->format('N'); // 1=Mon .. 7=Sun
        $monday = $today->modify('-' . ($dow - 1) . ' days');
        $percents = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $monday->modify('+' . $i . ' day');
            $dayHabits = $this->habits->todayHabits($userId, $day->format('Y-m-d'));
            $percents[] = $this->complianceRatio($dayHabits);
        }
        return $percents;
    }

    /**
     * @return list<array{area_id: ?int, area_name: string, area_color: ?string, count: int}>
     */
    private function areaDistribution(int $userId, int $year): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT g.area_id,
                    COALESCE(la.name, \'Sin área\') AS area_name,
                    la.color AS area_color,
                    COUNT(*) AS count
             FROM goals g
             LEFT JOIN life_areas la ON la.id = g.area_id AND la.user_id = g.user_id
             WHERE g.user_id = :user_id
               AND g.deleted_at IS NULL
               AND g.period_year = :year
               AND g.horizon <> \'largo_plazo\'
             GROUP BY g.area_id, la.name, la.color
             ORDER BY count DESC, area_name ASC'
        );
        $stmt->execute(['user_id' => $userId, 'year' => $year]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'area_id' => $row['area_id'] !== null ? (int) $row['area_id'] : null,
                'area_name' => (string) $row['area_name'],
                'area_color' => $row['area_color'] !== null ? (string) $row['area_color'] : null,
                'count' => (int) $row['count'],
            ];
        }
        return $rows;
    }

    /**
     * Last N weeks of daily compliance (value 0–1).
     *
     * @return list<array{date: string, value: float}>
     */
    private function habitHeatmap(int $userId, DateTimeImmutable $today, int $weeks): array
    {
        $days = $weeks * 7;
        $start = $today->modify('-' . ($days - 1) . ' days');

        $activeHabits = $this->habits->listActive($userId);
        $habitIds = array_map(static fn(array $h): int => (int) $h['id'], $activeHabits);
        $scheduleMap = [];
        if ($habitIds !== []) {
            $placeholders = implode(',', array_fill(0, count($habitIds), '?'));
            $schedStmt = Database::pdo()->prepare(
                "SELECT habit_id, weekday FROM habit_schedule_days WHERE habit_id IN ($placeholders)"
            );
            $schedStmt->execute($habitIds);
            foreach ($schedStmt->fetchAll() as $row) {
                $scheduleMap[(int) $row['habit_id']][] = (int) $row['weekday'];
            }
        }

        $logsByDate = [];
        if ($habitIds !== []) {
            $placeholders = implode(',', array_fill(0, count($habitIds), '?'));
            $params = array_merge($habitIds, [$start->format('Y-m-d'), $today->format('Y-m-d')]);
            $logStmt = Database::pdo()->prepare(
                "SELECT habit_id, log_date, status
                 FROM habit_logs
                 WHERE habit_id IN ($placeholders)
                   AND log_date BETWEEN ? AND ?"
            );
            $logStmt->execute($params);
            foreach ($logStmt->fetchAll() as $row) {
                $logsByDate[$row['log_date']][(int) $row['habit_id']] = $row['status'];
            }
        }

        $heatmap = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->modify('+' . $i . ' day');
            $dateStr = $day->format('Y-m-d');
            $scheduled = 0;
            $done = 0;
            foreach ($activeHabits as $habit) {
                $habit['schedule_days'] = $scheduleMap[(int) $habit['id']] ?? [];
                if (!$this->habits->isScheduledOn($habit, $day)) {
                    continue;
                }
                $scheduled++;
                $status = $logsByDate[$dateStr][(int) $habit['id']] ?? null;
                if ($status === 'completed' || $status === 'partial') {
                    $done++;
                }
            }
            $heatmap[] = [
                'date' => $dateStr,
                'value' => $scheduled > 0 ? round($done / $scheduled, 4) : 0.0,
            ];
        }

        return $heatmap;
    }

    /** @param list<array<string, mixed>> $dayHabits */
    private function complianceRatio(array $dayHabits): float
    {
        $total = count($dayHabits);
        if ($total === 0) {
            return 0.0;
        }
        $done = 0;
        foreach ($dayHabits as $habit) {
            $status = $habit['log_status'] ?? ($habit['log']['status'] ?? null);
            if ($status === 'completed' || $status === 'partial') {
                $done++;
            }
        }
        return round(($done / $total) * 100, 2);
    }
}
