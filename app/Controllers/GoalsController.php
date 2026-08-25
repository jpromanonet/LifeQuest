<?php

declare(strict_types=1);

final class GoalsController
{
    private GoalService $goals;
    private AnnualPlanService $plans;
    private AuditService $audit;

    public function __construct()
    {
        $this->goals = new GoalService();
        $this->plans = new AnnualPlanService();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $year = (int) (input('year') ?: now_local()->format('Y'));
        $currentYear = (int) now_local()->format('Y');

        // No recrear años eliminados al visitarlos por URL: solo el actual o los ya listados.
        $knownYears = $this->plans->years($userId);
        if ($year !== $currentYear && !in_array($year, $knownYears, true)) {
            flash('error', 'Ese año no está disponible. Podés crearlo con «+ Año».');
            redirect('/goals?year=' . $currentYear);
        }

        $filters = [
            'year' => $year,
            'area_id' => input('area') ?: input('area_id') ?: null,
            'status' => input('status') ?: null,
            'priority' => input('priority') ?: null,
            'q' => input('q') ?: null,
            'exclude_horizon' => true,
            'area_scope' => 'annual',
        ];

        (new AreaService())->ensureCanonicalAreas($userId);
        $this->plans->ensureYear($userId, $year);
        (new BookService())->ensureAnnualGoal($userId, $year);
        $grouped = $this->goals->groupedByArea($userId, $year);

        foreach ($grouped as &$section) {
            $section['goals'] = $this->goals->attachMonthProgress($userId, $section['goals']);
        }
        unset($section);

        if (!empty($filters['area_id']) || !empty($filters['status']) || !empty($filters['priority']) || !empty($filters['q'])) {
            $filtered = $this->goals->list($userId, array_filter([
                'year' => $year,
                'area_id' => $filters['area_id'],
                'status' => $filters['status'],
                'priority' => $filters['priority'],
                'q' => $filters['q'],
                'exclude_horizon' => true,
                'area_scope' => 'annual',
            ], static fn($v) => $v !== null && $v !== ''));
            $filtered = $this->goals->attachMonthProgress($userId, $filtered);
            $byArea = [];
            foreach ($filtered as $goal) {
                $aid = $goal['area_id'] !== null ? (int) $goal['area_id'] : 0;
                $byArea[$aid][] = $goal;
            }
            $regrouped = [];
            foreach ($grouped as $section) {
                $aid = (int) $section['area']['id'];
                if (!isset($byArea[$aid])) {
                    continue;
                }
                $regrouped[] = [
                    'area' => $section['area'],
                    'goals' => $byArea[$aid],
                ];
            }
            $grouped = $regrouped;
        }

        $counts = $this->goals->countsByStatus($userId, $year);
        $metrics = (new MetricsService())->overview($userId, ['year' => $year]);

        $selected = null;
        $selectedMilestones = [];
        $selectedActions = [];
        $selectedMonths = MonthProgress::emptyMap();
        $selectedId = (int) (input('id') ?: 0);
        if ($selectedId > 0) {
            $selected = $this->goals->find($userId, $selectedId);
            if ($selected && ($selected['horizon'] ?? '') === 'largo_plazo') {
                redirect('/horizon?id=' . $selectedId);
            }
            if ($selected) {
                $this->goals->ensureMonthMode($userId, $selectedId);
                $monthProgress = $this->goals->monthProgress($userId, $selectedId);
                $selected['progress_percent'] = $monthProgress['percent'];
                $selected['month_checks'] = $monthProgress['months'];
                $selectedMonths = $monthProgress['months'];
                $selectedMilestones = $this->goals->milestones($selectedId);
                $selectedActions = $this->goals->actions($selectedId);
            }
        }

        $areas = $this->listAreas($userId, 'annual');

        view('goals/index', [
            'title' => 'Objetivos',
            'currentNav' => 'goals',
            'year' => $year,
            'availableYears' => $this->plans->years($userId),
            'filters' => $filters,
            'grouped' => $grouped,
            'counts' => $counts,
            'atRisk' => $metrics['goals_at_risk'],
            'areas' => $areas,
            'selected' => $selected,
            'selectedMonths' => $selectedMonths,
            'selectedMilestones' => $selectedMilestones,
            'selectedActions' => $selectedActions,
            'monthLabels' => MonthProgress::MONTH_LABELS,
            'flashSuccess' => flash('success'),
            'flashError' => flash('error'),
        ]);
    }

    public function show(string $id): void
    {
        Auth::requireLogin();
        $goalId = (int) $id;
        $goal = $this->goals->find(Auth::id(), $goalId);
        if ($goal === null) {
            if ($this->wantsJson()) {
                json_response(['error' => 'Objetivo no encontrado'], 404);
            }
            http_response_code(404);
            view('errors/404', ['title' => 'No encontrada']);
            return;
        }

        $payload = [
            'goal' => $goal,
            'milestones' => $this->goals->milestones($goalId),
            'actions' => $this->goals->actions($goalId),
            'month_progress' => $this->goals->monthProgress(Auth::id(), $goalId),
        ];

        if ($this->wantsJson()) {
            json_response($payload);
        }

        view('goals/detail', [
            'title' => $goal['title'],
            'currentNav' => 'goals',
            'selected' => $goal,
            'selectedMilestones' => $payload['milestones'],
            'selectedActions' => $payload['actions'],
        ], null);
    }

    public function store(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();

        try {
            $data = $this->goalPayloadFromRequest();
            if ($data['title'] === '') {
                respond_error('El título es obligatorio.', '/goals');
            }
            if (!array_key_exists('progress_percent', $data)) {
                $data['progress_percent'] = 0;
            }
            $id = $this->goals->create($userId, $data);
            $this->normalizeProgress($userId, $id, (string) ($data['progress_mode'] ?? 'months'));
            $this->audit->log($userId, 'goal.create', 'goal', $id, ['title' => $data['title'] ?? null]);
            $year = (int) ($data['period_year'] ?? now_local()->format('Y'));
            respond_saved('Objetivo creado.', '/goals?year=' . $year);
        } catch (Throwable $e) {
            $msg = 'No se pudo crear el objetivo.';
            if ((bool) app_config('debug', false)) {
                $msg .= ' ' . $e->getMessage();
            }
            respond_error($msg, '/goals');
        }
    }

    public function update(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $goalId = (int) $id;

        try {
            $data = $this->goalPayloadFromRequest();
            if ($data['title'] === '') {
                respond_error('El título es obligatorio.', '/goals?id=' . $goalId);
            }
            $this->goals->update($userId, $goalId, $data);
            $this->normalizeProgress($userId, $goalId, (string) ($data['progress_mode'] ?? 'months'));
            $this->audit->log($userId, 'goal.update', 'goal', $goalId);
            $year = (int) ($data['period_year'] ?? now_local()->format('Y'));
            respond_saved('Objetivo actualizado.', '/goals?year=' . $year);
        } catch (Throwable $e) {
            $msg = 'No se pudo actualizar el objetivo.';
            if ((bool) app_config('debug', false)) {
                $msg .= ' ' . $e->getMessage();
            }
            respond_error($msg, '/goals?id=' . $goalId);
        }
    }

    public function destroy(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $goalId = (int) $id;

        try {
            $goal = $this->goals->find($userId, $goalId);
            $year = (int) (
                input('year')
                ?: input('period_year')
                ?: ($goal['period_year'] ?? now_local()->format('Y'))
            );
            $this->goals->softDelete($userId, $goalId);
            $this->audit->log($userId, 'goal.delete', 'goal', $goalId);
            respond_saved('Objetivo eliminado.', '/goals?year=' . $year);
        } catch (Throwable $e) {
            $fallbackYear = (int) (input('year') ?: input('period_year') ?: now_local()->format('Y'));
            respond_error('No se pudo eliminar el objetivo.', '/goals?year=' . $fallbackYear);
        }
    }

    public function progress(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $goalId = (int) $id;

        try {
            $this->goals->updateProgress($userId, $goalId, [
                'progress_percent' => input('progress_percent'),
                'current_value' => input('current_value'),
                'comment' => input('comment'),
                'value_delta' => input('value_delta'),
                'evidence_url' => input('evidence_url'),
                'log_date' => input('log_date') ?: now_local()->format('Y-m-d'),
            ]);
            $this->audit->log($userId, 'goal.progress', 'goal', $goalId);
            if ($this->wantsJson()) {
                json_response(['ok' => true]);
            }
            flash('success', 'Progreso actualizado.');
            redirect('/goals?id=' . $goalId);
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                json_response(['ok' => false, 'error' => 'No se pudo actualizar el progreso'], 422);
            }
            flash('error', 'No se pudo actualizar el progreso.');
            redirect('/goals?id=' . $goalId);
        }
    }

    public function toggleMonth(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $goalId = (int) $id;
        $month = (int) (input('month') ?: 0);

        try {
            $this->goals->ensureMonthMode($userId, $goalId);
            $progress = $this->goals->toggleMonth($userId, $goalId, $month);
            $this->audit->log($userId, 'goal.month_toggle', 'goal', $goalId, ['month' => $month]);
            if ($this->wantsJson()) {
                json_response(['ok' => true, 'progress' => $progress]);
            }
            $goal = $this->goals->find($userId, $goalId);
            $year = (int) ($goal['period_year'] ?? now_local()->format('Y'));
            $redirectTo = (string) (input('redirect') ?: '');
            if ($redirectTo !== '' && str_starts_with($redirectTo, '/')) {
                flash('success', 'Mes actualizado.');
                redirect($redirectTo);
            }
            flash('success', 'Mes actualizado.');
            redirect('/goals?year=' . $year . '&id=' . $goalId);
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                json_response(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            flash('error', 'No se pudo actualizar el mes.');
            redirect('/goals?id=' . $goalId);
        }
    }

    public function toggleBinary(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $goalId = (int) $id;
        try {
            $progress = $this->goals->toggleBinary($userId, $goalId);
            $this->audit->log($userId, 'goal.binary_toggle', 'goal', $goalId, $progress);
            if ($this->wantsJson()) {
                json_response(['ok' => true, 'progress' => $progress]);
            }
            $goal = $this->goals->find($userId, $goalId);
            $year = (int) ($goal['period_year'] ?? now_local()->format('Y'));
            flash('success', $progress['done'] ? 'Marcado como hecho.' : 'Marcado como pendiente.');
            redirect('/goals?year=' . $year . '&id=' . $goalId);
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                json_response(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            flash('error', 'No se pudo actualizar.');
            redirect('/goals?id=' . $goalId);
        }
    }

    public function quantity(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $goalId = (int) $id;

        $currentInput = input('current_value');
        $deltaInput = input('delta');
        $targetInput = input('target_value');
        $unitInput = input('unit');

        try {
            $current = null;
            if ($currentInput !== null && $currentInput !== '') {
                $current = (float) $currentInput;
            } elseif ($deltaInput !== null && $deltaInput !== '') {
                $goal = $this->goals->find($userId, $goalId);
                if ($goal === null) {
                    throw new RuntimeException('Objetivo no encontrado');
                }
                $current = (float) ($goal['current_value'] ?? 0) + (float) $deltaInput;
            }

            $progress = $this->goals->setQuantity(
                $userId,
                $goalId,
                $current,
                $targetInput !== null && $targetInput !== '' ? (float) $targetInput : null,
                $unitInput !== null && $unitInput !== '' ? (string) $unitInput : null
            );
            $this->audit->log($userId, 'goal.quantity', 'goal', $goalId, $progress);

            if ($this->wantsJson()) {
                json_response(['ok' => true, 'progress' => $progress]);
            }
            $goal = $this->goals->find($userId, $goalId);
            $year = (int) ($goal['period_year'] ?? now_local()->format('Y'));
            flash('success', 'Unidades actualizadas.');
            redirect('/goals?year=' . $year . '&id=' . $goalId);
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                json_response(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            flash('error', 'No se pudieron actualizar las unidades.');
            redirect('/goals?id=' . $goalId);
        }
    }

    public function addYear(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $year = (int) (input('year') ?: 0);
        if ($year < 2000 || $year > 2100) {
            respond_error('Año inválido.', '/goals');
        }
        try {
            (new AreaService())->ensureCanonicalAreas($userId);
            $this->plans->ensureYear($userId, $year);
            (new BookService())->ensureAnnualGoal($userId, $year);
            respond_saved('Año ' . $year . ' listo con áreas vacías.', '/goals?year=' . $year);
        } catch (Throwable $e) {
            respond_error('No se pudo crear el año: ' . $e->getMessage(), '/goals');
        }
    }

    public function destroyYear(string $year): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $target = (int) $year;

        try {
            $result = $this->plans->deleteYear($userId, $target);
            $this->audit->log($userId, 'goal.year_delete', 'annual_plan', null, [
                'year' => $target,
                'goals' => $result['goals'],
            ]);
            $msg = 'Año ' . $target . ' eliminado (' . $result['goals'] . ' objetivos archivados).';
        } catch (InvalidArgumentException $e) {
            // Nunca volver al año borrado: ensureYear lo recrearía.
            $remaining = $this->plans->years($userId);
            $fallback = (int) now_local()->format('Y');
            if ($remaining !== []) {
                $fallback = (int) $remaining[count($remaining) - 1];
            }
            respond_error($e->getMessage(), '/goals?year=' . $fallback);
        } catch (Throwable $e) {
            respond_error('No se pudo eliminar el año: ' . $e->getMessage(), '/goals');
        }

        // Nunca volver al año borrado: ensureYear lo recrearía.
        $remaining = $this->plans->years($userId);
        $fallback = (int) now_local()->format('Y');
        if ($remaining !== []) {
            $earlier = array_filter($remaining, static fn (int $y): bool => $y < $target);
            $later = array_filter($remaining, static fn (int $y): bool => $y > $target);
            if ($earlier !== []) {
                $fallback = max($earlier);
            } elseif ($later !== []) {
                $fallback = min($later);
            } elseif (in_array($fallback, $remaining, true)) {
                // año actual ok
            } else {
                $fallback = (int) $remaining[count($remaining) - 1];
            }
        }
        respond_saved($msg, '/goals?year=' . $fallback);
    }

    /** @return array<string, mixed> */
    private function goalPayloadFromRequest(): array
    {
        $areaId = input('area_id');
        $year = input('period_year') ?: input('year') ?: now_local()->format('Y');
        $mode = (string) (input('progress_mode') ?: 'months');
        if (!in_array($mode, ['months', 'quantity', 'binary'], true)) {
            $mode = 'months';
        }

        $data = [
            'title' => trim((string) input('title', '')),
            'description' => input('description'),
            'goal_type' => input('goal_type') ?: 'goal',
            'area_id' => $areaId !== null && $areaId !== '' ? (int) $areaId : null,
            'impact_area_id' => input('impact_area_id') ?: null,
            'horizon' => 'anual',
            'period_year' => (int) $year,
            'period_quarter' => input('period_quarter') ?: null,
            'period_month' => input('period_month') ?: null,
            'status' => input('status') ?: 'planned',
            'priority' => input('priority') ?: 'medium',
            'progress_mode' => $mode,
            'start_date' => input('start_date') ?: null,
            'due_date' => input('due_date') ?: null,
            'success_criteria' => input('success_criteria'),
            'motivation' => input('motivation'),
            'next_action' => input('next_action'),
            'external_system' => input('external_system'),
            'external_url' => input('external_url'),
            'goal_key' => input('goal_key') ?: null,
        ];

        if ($mode === 'quantity') {
            $target = input('target_value');
            $data['target_value'] = $target !== null && $target !== '' ? (float) $target : 12.0;
            $data['current_value'] = input('current_value') !== null && input('current_value') !== ''
                ? (float) input('current_value')
                : null;
        }

        return $data;
    }

    /** Recalcula el progreso según el modo elegido. */
    private function normalizeProgress(int $userId, int $goalId, string $mode): void
    {
        if ($mode === 'quantity') {
            $this->goals->setQuantity($userId, $goalId);
            return;
        }
        if ($mode === 'binary') {
            $goal = $this->goals->find($userId, $goalId);
            if ($goal === null) {
                return;
            }
            $done = (float) ($goal['progress_percent'] ?? 0) >= 100
                || ($goal['status'] ?? '') === 'completed';
            $this->goals->setBinary($userId, $goalId, $done);
            return;
        }
        if ($mode === 'months') {
            $this->goals->ensureMonthMode($userId, $goalId);
        }
    }

    /** @return list<array<string, mixed>> */
    private function listAreas(int $userId, string $scope = 'annual'): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, area_key, name, color, scope
             FROM life_areas
             WHERE user_id = :user_id AND deleted_at IS NULL AND scope = :scope
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['user_id' => $userId, 'scope' => $scope]);
        return $stmt->fetchAll();
    }

    private function wantsJson(): bool
    {
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strcasecmp((string) $xhr, 'XMLHttpRequest') === 0;
    }
}
