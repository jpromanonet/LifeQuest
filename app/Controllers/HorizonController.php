<?php

declare(strict_types=1);

final class HorizonController
{
    private GoalService $goals;
    private AuditService $audit;

    public function __construct()
    {
        $this->goals = new GoalService();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        (new AreaService())->ensureCanonicalAreas($userId);
        $this->goals->forceBinaryHorizon($userId);
        $grouped = $this->goals->groupedByHorizonArea($userId);
        foreach ($grouped as &$section) {
            $section['goals'] = $this->goals->attachMonthProgress($userId, $section['goals']);
        }
        unset($section);
        $areas = $this->listHorizonAreas($userId);

        $filters = [
            'area_id' => (int) (input('area') ?: 0),
            'done' => (string) (input('done') ?: ''),
        ];
        if (!in_array($filters['done'], ['yes', 'no'], true)) {
            $filters['done'] = '';
        }

        $selected = null;
        $selectedId = (int) (input('id') ?: 0);
        if ($selectedId > 0) {
            $selected = $this->goals->find($userId, $selectedId);
            if ($selected && ($selected['horizon'] ?? '') !== 'largo_plazo') {
                redirect('/goals?id=' . $selectedId);
            }
        }

        $totals = ['all' => 0, 'achieved' => 0, 'in_progress' => 0];
        foreach ($grouped as $section) {
            foreach ($section['goals'] as $goal) {
                $totals['all']++;
                if ($this->isHorizonDone($goal)) {
                    $totals['achieved']++;
                } else {
                    $totals['in_progress']++;
                }
            }
        }

        $filtered = $this->applyFilters($grouped, $filters);

        view('horizon/index', [
            'title' => 'Horizonte',
            'currentNav' => 'horizon',
            'grouped' => $filtered,
            'areas' => $areas,
            'selected' => $selected,
            'totals' => $totals,
            'filters' => $filters,
            'flashSuccess' => flash('success'),
            'flashError' => flash('error'),
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();

        try {
            $id = $this->goals->create($userId, $this->payloadFromRequest());
            $this->audit->log($userId, 'horizon.create', 'goal', $id);
            respond_saved('Horizonte creado.', $this->horizonReturnPath());
        } catch (Throwable $e) {
            respond_error('No se pudo crear el horizonte.', $this->horizonReturnPath());
        }
    }

    public function update(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $goalId = (int) $id;
        $existing = $this->goals->find($userId, $goalId);
        if (!$existing || ($existing['horizon'] ?? '') !== 'largo_plazo') {
            respond_error('Horizonte no encontrado.', $this->horizonReturnPath());
        }

        try {
            $this->goals->update($userId, $goalId, $this->payloadFromRequest());
            $this->audit->log($userId, 'horizon.update', 'goal', $goalId);
            respond_saved('Horizonte actualizado.', $this->horizonReturnPath());
        } catch (Throwable $e) {
            respond_error('No se pudo actualizar el horizonte.', $this->horizonReturnPath());
        }
    }

    public function destroy(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $goalId = (int) $id;
        $existing = $this->goals->find($userId, $goalId);
        if (!$existing || ($existing['horizon'] ?? '') !== 'largo_plazo') {
            respond_error('Horizonte no encontrado.', $this->horizonReturnPath());
        }

        try {
            $this->goals->softDelete($userId, $goalId);
            $this->audit->log($userId, 'horizon.delete', 'goal', $goalId);
            respond_saved('Horizonte archivado.', $this->horizonReturnPath());
        } catch (Throwable $e) {
            respond_error('No se pudo eliminar el horizonte.', $this->horizonReturnPath());
        }
    }

    /** @return array<string, mixed> */
    private function payloadFromRequest(): array
    {
        $areaId = input('area_id');
        $status = (string) (input('status') ?: 'planned');

        return [
            'title' => trim((string) input('title', '')),
            'description' => input('description'),
            'goal_type' => 'goal',
            'area_id' => $areaId !== null && $areaId !== '' ? (int) $areaId : null,
            'horizon' => 'largo_plazo',
            'period_year' => null,
            'status' => $status,
            'priority' => input('priority') ?: 'medium',
            'progress_mode' => 'binary',
            'success_criteria' => input('success_criteria'),
            'motivation' => input('motivation'),
            'next_action' => input('next_action'),
            'external_system' => input('external_system'),
            'external_url' => input('external_url'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function listHorizonAreas(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, area_key, name, color
             FROM life_areas
             WHERE user_id = :user_id AND deleted_at IS NULL AND scope = \'horizon\'
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $goal */
    private function isHorizonDone(array $goal): bool
    {
        return (float) ($goal['progress_percent'] ?? 0) >= 100
            || ($goal['status'] ?? '') === 'completed';
    }

    /**
     * @param list<array{area:array,goals:list}> $grouped
     * @param array{area_id:int,done:string} $filters
     * @return list<array{area:array,goals:list}>
     */
    private function applyFilters(array $grouped, array $filters): array
    {
        $areaId = (int) ($filters['area_id'] ?? 0);
        $done = (string) ($filters['done'] ?? '');
        $out = [];

        foreach ($grouped as $section) {
            if ($areaId > 0 && (int) ($section['area']['id'] ?? 0) !== $areaId) {
                continue;
            }

            $goals = $section['goals'];
            if ($done === 'yes' || $done === 'no') {
                $wantDone = $done === 'yes';
                $goals = array_values(array_filter(
                    $goals,
                    fn (array $goal): bool => $this->isHorizonDone($goal) === $wantDone
                ));
            }

            if ($goals === [] && ($areaId > 0 || $done !== '')) {
                // Con filtro activo, ocultar secciones vacías.
                continue;
            }

            $out[] = [
                'area' => $section['area'],
                'goals' => $goals,
            ];
        }

        return $out;
    }

    private function horizonReturnPath(): string
    {
        $params = [];
        $area = (int) (input('filter_area') ?: input('area') ?: 0);
        $done = (string) (input('filter_done') ?: input('done') ?: '');
        if ($area > 0) {
            $params['area'] = $area;
        }
        if (in_array($done, ['yes', 'no'], true)) {
            $params['done'] = $done;
        }
        return $params === [] ? '/horizon' : '/horizon?' . http_build_query($params);
    }
}
