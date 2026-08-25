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

        $selected = null;
        $selectedId = (int) (input('id') ?: 0);
        if ($selectedId > 0) {
            $selected = $this->goals->find($userId, $selectedId);
            if ($selected && ($selected['horizon'] ?? '') !== 'largo_plazo') {
                redirect('/goals?id=' . $selectedId);
            }
        }

        $totals = ['planned' => 0, 'active' => 0, 'completed' => 0, 'all' => 0];
        foreach ($grouped as $section) {
            foreach ($section['goals'] as $goal) {
                $totals['all']++;
                $st = (string) ($goal['status'] ?? '');
                if (isset($totals[$st])) {
                    $totals[$st]++;
                }
            }
        }

        view('horizon/index', [
            'title' => 'Horizonte',
            'currentNav' => 'horizon',
            'grouped' => $grouped,
            'areas' => $areas,
            'selected' => $selected,
            'totals' => $totals,
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
            respond_saved('Horizonte creado.', '/horizon');
        } catch (Throwable $e) {
            respond_error('No se pudo crear el horizonte.', '/horizon');
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
            respond_error('Horizonte no encontrado.', '/horizon');
        }

        try {
            $this->goals->update($userId, $goalId, $this->payloadFromRequest());
            $this->audit->log($userId, 'horizon.update', 'goal', $goalId);
            respond_saved('Horizonte actualizado.', '/horizon');
        } catch (Throwable $e) {
            respond_error('No se pudo actualizar el horizonte.', '/horizon');
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
            respond_error('Horizonte no encontrado.', '/horizon');
        }

        try {
            $this->goals->softDelete($userId, $goalId);
            $this->audit->log($userId, 'horizon.delete', 'goal', $goalId);
            respond_saved('Horizonte archivado.', '/horizon');
        } catch (Throwable $e) {
            respond_error('No se pudo eliminar el horizonte.', '/horizon');
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
}
