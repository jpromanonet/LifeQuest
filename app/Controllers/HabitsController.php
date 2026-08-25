<?php

declare(strict_types=1);

final class HabitsController
{
    private HabitService $habits;
    private AuditService $audit;

    public function __construct()
    {
        $this->habits = new HabitService();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $list = $this->habits->listActive($userId);
        $today = now_local()->format('Y-m-d');
        $logsToday = $this->habits->logsForDate($userId, $today);

        foreach ($list as &$habit) {
            $habit['log'] = $logsToday[(int) $habit['id']] ?? null;
            $habit['log_status'] = $habit['log']['status'] ?? null;
        }
        unset($habit);

        $selected = null;
        $streak = ['current_streak' => 0, 'best_streak' => 0];
        $recentLogs = [];
        $selectedMonths = MonthProgress::emptyMap();
        $habitYear = (int) now_local()->format('Y');
        $selectedId = (int) (input('id') ?: 0);
        if ($selectedId > 0) {
            $selected = $this->habits->find($userId, $selectedId);
            if ($selected) {
                $streak = $this->habits->streak($selectedId);
                $recentLogs = $this->recentLogs($selectedId, 30);
                if (($selected['tracking_mode'] ?? '') === 'months') {
                    $mp = $this->habits->monthProgress($userId, $selectedId, $habitYear);
                    $selectedMonths = $mp['months'];
                    $selected['progress_percent'] = $mp['percent'];
                    $selected['months_checked'] = $mp['checked'];
                }
            }
        }

        $areas = $this->listAreas($userId);

        view('habits/index', [
            'title' => 'Hábitos',
            'currentNav' => 'habits',
            'habits' => $list,
            'selected' => $selected,
            'selectedMonths' => $selectedMonths,
            'monthLabels' => MonthProgress::MONTH_LABELS,
            'habitYear' => $habitYear,
            'streak' => $streak,
            'recentLogs' => $recentLogs,
            'areas' => $areas,
            'todayDate' => $today,
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
            $payload = $this->habitPayloadFromRequest();
            if ($payload['name'] === '') {
                throw new InvalidArgumentException('El nombre del hábito es obligatorio.');
            }
            $id = $this->habits->create($userId, $payload);
            $this->audit->log($userId, 'habit.create', 'habit', $id);
            respond_saved('Hábito creado.', '/habits?id=' . $id);
        } catch (Throwable $e) {
            respond_error('No se pudo crear el hábito: ' . $e->getMessage(), '/habits');
        }
    }

    public function update(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $habitId = (int) $id;

        try {
            $payload = $this->habitPayloadFromRequest();
            if ($payload['name'] === '') {
                throw new InvalidArgumentException('El nombre del hábito es obligatorio.');
            }
            $this->habits->update($userId, $habitId, $payload);
            $this->audit->log($userId, 'habit.update', 'habit', $habitId);
            respond_saved('Hábito actualizado.', '/habits?id=' . $habitId);
        } catch (Throwable $e) {
            respond_error('No se pudo actualizar el hábito: ' . $e->getMessage(), '/habits?id=' . $habitId);
        }
    }

    public function archive(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $habitId = (int) $id;

        try {
            $this->habits->archive($userId, $habitId);
            $this->audit->log($userId, 'habit.archive', 'habit', $habitId);
            respond_saved('Hábito archivado.', '/habits');
        } catch (Throwable $e) {
            respond_error('No se pudo archivar el hábito.', '/habits');
        }
    }

    public function destroy(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $habitId = (int) $id;

        try {
            $this->habits->delete($userId, $habitId);
            $this->audit->log($userId, 'habit.delete', 'habit', $habitId);
            respond_saved('Hábito eliminado.', '/habits');
        } catch (Throwable $e) {
            respond_error('No se pudo eliminar el hábito.', '/habits');
        }
    }

    public function reorder(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();

        $ids = input('order') ?? input('ids') ?? [];
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : array_filter(array_map('intval', explode(',', $ids)));
        }
        if (!is_array($ids)) {
            $ids = [];
        }

        try {
            $this->habits->reorder($userId, array_map('intval', $ids));
            $this->audit->log($userId, 'habit.reorder', 'habit', null, ['order' => $ids]);
            if ($this->wantsJson()) {
                json_response(['ok' => true]);
            }
            flash('success', 'Orden actualizado.');
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                json_response(['ok' => false], 422);
            }
            flash('error', 'No se pudo reordenar.');
        }
        redirect('/habits');
    }

    public function log(?string $id = null): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();

        $habitId = (int) ($id ?: input('habit_id', 0));
        $status = (string) input('status', 'completed');
        $date = (string) (input('date') ?: input('log_date') ?: now_local()->format('Y-m-d'));
        $quantity = input('quantity');
        $note = input('note');

        $qty = $quantity !== null && $quantity !== '' ? (float) $quantity : null;

        try {
            $this->habits->log($userId, $habitId, $date, $status, $qty, $note !== null ? (string) $note : null);
            $this->audit->log($userId, 'habit.log', 'habit', $habitId, [
                'date' => $date,
                'status' => $status,
            ]);

            if ($this->wantsJson()) {
                json_response([
                    'ok' => true,
                    'habit_id' => $habitId,
                    'date' => $date,
                    'status' => $status,
                ]);
            }

            $redirectTo = (string) (input('redirect') ?: '/today');
            redirect($redirectTo);
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                json_response(['ok' => false, 'error' => 'No se pudo registrar el hábito'], 422);
            }
            flash('error', 'No se pudo registrar el hábito.');
            redirect((string) (input('redirect') ?: '/habits'));
        }
    }

    public function toggleMonth(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $habitId = (int) $id;
        $month = (int) (input('month') ?: 0);
        $year = (int) (input('year') ?: now_local()->format('Y'));

        try {
            $progress = $this->habits->toggleMonth($userId, $habitId, $year, $month);
            $this->audit->log($userId, 'habit.month_toggle', 'habit', $habitId, [
                'year' => $year,
                'month' => $month,
            ]);
            if ($this->wantsJson()) {
                json_response(['ok' => true, 'progress' => $progress]);
            }
            flash('success', 'Mes actualizado.');
            redirect('/habits?id=' . $habitId);
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                json_response(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            flash('error', 'No se pudo actualizar el mes.');
            redirect('/habits?id=' . $habitId);
        }
    }

    public function bumpUnits(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $habitId = (int) $id;
        $delta = input('delta');
        $set = input('current_value');

        try {
            if ($set !== null && $set !== '') {
                $result = $this->habits->setUnits($userId, $habitId, (float) $set);
            } else {
                $result = $this->habits->bumpUnits($userId, $habitId, $delta !== null && $delta !== '' ? (float) $delta : 1.0);
            }
            $this->audit->log($userId, 'habit.units', 'habit', $habitId, $result);
            if ($this->wantsJson()) {
                json_response(['ok' => true, 'progress' => $result]);
            }
            flash('success', 'Progreso actualizado.');
            redirect('/habits?id=' . $habitId);
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                json_response(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            flash('error', 'No se pudo actualizar las unidades.');
            redirect('/habits?id=' . $habitId);
        }
    }

    /** @return array<string, mixed> */
    private function habitPayloadFromRequest(): array
    {
        $days = input('days') ?? input('schedule_days');
        if (is_string($days)) {
            $days = array_filter(array_map('intval', explode(',', $days)));
        }

        $areaId = input('area_id');
        $tracking = (string) (input('tracking_mode') ?: 'months');
        if (!in_array($tracking, ['months', 'units', 'daily'], true)) {
            $tracking = 'months';
        }

        $unitDefault = $tracking === 'units' ? 'unidad' : ($tracking === 'months' ? 'mes' : 'vez');

        $payload = [
            'name' => trim((string) input('name', '')),
            'description' => input('description'),
            'area_id' => $areaId !== null && $areaId !== '' ? (int) $areaId : null,
            'frequency_type' => input('frequency_type') ?: ($tracking === 'daily' ? 'daily' : 'monthly'),
            'tracking_mode' => $tracking,
            'target_per_period' => (int) (input('target_per_period') ?: ($tracking === 'months' ? 12 : 1)),
            'unit' => input('unit') ?: $unitDefault,
            'minimum_value' => input('minimum_value') ?: null,
            'preferred_time' => input('preferred_time') ?: 'anytime',
            'start_date' => input('start_date') ?: null,
            'end_date' => input('end_date') ?: null,
            'linked_goal_id' => input('linked_goal_id') ?: null,
            'notes' => input('notes'),
            'days' => is_array($days) ? $days : null,
            'habit_key' => input('habit_key') ?: null,
        ];

        $currentValue = input('current_value');
        if ($currentValue !== null && $currentValue !== '') {
            $payload['current_value'] = (float) $currentValue;
        }

        return $payload;
    }

    /** @return list<array<string, mixed>> */
    private function recentLogs(int $habitId, int $limit): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM habit_logs
             WHERE habit_id = :habit_id
             ORDER BY log_date DESC
             LIMIT ' . max(1, $limit)
        );
        $stmt->execute(['habit_id' => $habitId]);
        return $stmt->fetchAll();
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

    private function wantsJson(): bool
    {
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strcasecmp((string) $xhr, 'XMLHttpRequest') === 0;
    }
}
