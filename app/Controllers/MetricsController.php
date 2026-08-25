<?php

declare(strict_types=1);

final class MetricsController
{
    private MetricsService $metrics;
    private GoalService $goals;
    private HabitService $habits;

    public function __construct()
    {
        $this->habits = new HabitService();
        $this->goals = new GoalService();
        $this->metrics = new MetricsService($this->habits, $this->goals);
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $filters = $this->filtersFromRequest();
        $overview = $this->metrics->overview($userId, $filters);
        $areas = $this->listAreas($userId);

        $weekLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $weeklyChart = [
            'type' => 'bar',
            'data' => [
                'labels' => $weekLabels,
                'datasets' => [[
                    'label' => 'Cumplimiento %',
                    'data' => $overview['weekly_progress'],
                    'backgroundColor' => '#7C83E1',
                    'borderRadius' => 8,
                ]],
            ],
            'options' => [
                'scales' => [
                    'y' => ['beginAtZero' => true, 'max' => 100],
                ],
                'plugins' => ['legend' => ['display' => false]],
            ],
        ];

        $areaRows = $overview['areas'] ?? $overview['area_distribution'];
        $areaChart = [
            'type' => 'doughnut',
            'data' => [
                'labels' => array_map(
                    static fn(array $a): string => (string) ($a['name'] ?? $a['area_name'] ?? ''),
                    $areaRows
                ),
                'datasets' => [[
                    'data' => array_map(
                        static fn(array $a): int => (int) ($a['total'] ?? $a['count'] ?? 0),
                        $areaRows
                    ),
                    'backgroundColor' => array_map(
                        static fn(array $a): string => (string) ($a['color'] ?? $a['area_color'] ?? '#7C83E1'),
                        $areaRows
                    ),
                ]],
            ],
            'options' => [
                'plugins' => ['legend' => ['position' => 'bottom']],
            ],
        ];

        $heatmapLabels = [];
        $heatmapValues = [];
        foreach ($overview['habit_heatmap'] as $point) {
            $heatmapLabels[] = substr($point['date'], 5);
            $heatmapValues[] = round($point['value'] * 100, 1);
        }
        $heatmapChart = [
            'type' => 'line',
            'data' => [
                'labels' => $heatmapLabels,
                'datasets' => [[
                    'label' => 'Cumplimiento diario %',
                    'data' => $heatmapValues,
                    'borderColor' => '#78C6B0',
                    'backgroundColor' => 'rgba(120, 198, 176, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ]],
            ],
            'options' => [
                'scales' => [
                    'y' => ['beginAtZero' => true, 'max' => 100],
                ],
            ],
        ];

        view('metrics/index', [
            'title' => 'Métricas',
            'currentNav' => 'metrics',
            'filters' => $filters,
            'overview' => $overview,
            'areas' => $areas,
            'availableYears' => (new AnnualPlanService())->years($userId),
            'weeklyChart' => $weeklyChart,
            'areaChart' => $areaChart,
            'heatmapChart' => $heatmapChart,
        ]);
    }

    public function apiGoals(): void
    {
        Auth::requireLogin();
        $filters = $this->filtersFromRequest();
        $year = (int) ($filters['year'] ?? now_local()->format('Y'));
        $list = $this->goals->list(Auth::id(), array_filter([
            'year' => $year,
            'area_id' => $filters['area_id'] ?? null,
            'status' => $filters['status'] ?? null,
            'priority' => $filters['priority'] ?? null,
        ], static fn($v) => $v !== null && $v !== ''));
        $overview = $this->metrics->overview(Auth::id(), $filters);

        json_response([
            'overview' => [
                'goals_total' => $overview['goals_total'],
                'goals_completed' => $overview['goals_completed'],
                'goals_active' => $overview['goals_active'],
                'goals_at_risk' => $overview['goals_at_risk'],
                'completion_rate' => $overview['completion_rate'],
                'avg_progress' => $overview['avg_progress'],
                'area_distribution' => $overview['area_distribution'],
            ],
            'goals' => $list,
        ]);
    }

    public function apiHabits(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $filters = $this->filtersFromRequest();
        $overview = $this->metrics->overview($userId, $filters);
        $habits = $this->habits->listActive($userId);
        $streaks = [];
        foreach ($habits as $habit) {
            $streaks[(int) $habit['id']] = $this->habits->streak((int) $habit['id']);
        }

        json_response([
            'habits_compliance_today' => $overview['habits_compliance_today'],
            'current_streak_best' => $overview['current_streak_best'],
            'weekly_progress' => $overview['weekly_progress'],
            'habit_heatmap' => $overview['habit_heatmap'],
            'habits' => $habits,
            'streaks' => $streaks,
        ]);
    }

    public function apiConsolidated(): void
    {
        Auth::requireLogin();
        $filters = $this->filtersFromRequest();
        $overview = $this->metrics->overview(Auth::id(), $filters);
        json_response(['overview' => $overview, 'filters' => $filters]);
    }

    /** @return array<string, mixed> */
    private function filtersFromRequest(): array
    {
        $year = (int) (input('year') ?: now_local()->format('Y'));
        $compareRaw = input('compare_year');
        $compareYear = null;
        if ($compareRaw !== null && $compareRaw !== '') {
            $compareYear = (int) $compareRaw;
            if ($compareYear === $year) {
                $compareYear = $year - 1;
            }
        }

        return [
            'year' => $year,
            'compare_year' => $compareYear,
            'area_id' => input('area') ?: input('area_id') ?: null,
            'status' => input('status') ?: null,
            'priority' => input('priority') ?: null,
            'from' => input('from') ?: null,
            'to' => input('to') ?: null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function listAreas(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, area_key, name, color
             FROM life_areas
             WHERE user_id = :user_id AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
