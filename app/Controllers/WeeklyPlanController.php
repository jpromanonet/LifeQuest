<?php

declare(strict_types=1);

final class WeeklyPlanController
{
    private WeeklyPlanService $plan;
    private AuditService $audit;

    public function __construct()
    {
        $this->plan = new WeeklyPlanService();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $ref = $this->refFromRequest();
        $bounds = $this->plan->weekBounds($ref);
        $board = $this->plan->weekBoard($userId, $ref);
        $stats = $this->plan->weekStats($userId, $ref);

        $prevMonday = $bounds['monday']->modify('-7 days')->format('Y-m-d');
        $nextMonday = $bounds['monday']->modify('+7 days')->format('Y-m-d');
        $currentBounds = $this->plan->weekBounds();

        view('weekly/index', [
            'title' => 'Plan semanal',
            'currentNav' => 'weekly',
            'board' => $board,
            'stats' => $stats,
            'monday' => $bounds['monday'],
            'sunday' => $bounds['sunday'],
            'prevMonday' => $prevMonday,
            'nextMonday' => $nextMonday,
            'isCurrentWeek' => $bounds['monday']->format('Y-m-d') === $currentBounds['monday']->format('Y-m-d'),
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
            $id = $this->plan->create($userId, [
                'title' => input('title'),
                'task_date' => input('task_date'),
                'notes' => input('notes'),
            ]);
            $this->audit->log($userId, 'weekly.create', 'weekly_task', $id);
            $date = (string) input('task_date');
            $redirect = (string) (input('redirect') ?: '');
            if ($redirect !== '' && str_starts_with($redirect, '/')) {
                respond_saved('Tarea agregada.', $redirect);
            }
            respond_saved('Tarea agregada.', $this->redirectForDate($date));
        } catch (Throwable $e) {
            $fallback = (string) (input('redirect') ?: '/weekly');
            if (!str_starts_with($fallback, '/')) {
                $fallback = '/weekly';
            }
            respond_error('No se pudo crear la tarea: ' . $e->getMessage(), $fallback);
        }
    }

    public function update(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $taskId = (int) $id;
        try {
            $this->plan->update($userId, $taskId, [
                'title' => input('title'),
                'task_date' => input('task_date'),
                'notes' => input('notes'),
            ]);
            $this->audit->log($userId, 'weekly.update', 'weekly_task', $taskId);
            $date = (string) (input('task_date') ?: '');
            respond_saved('Tarea actualizada.', $this->redirectForDate($date));
        } catch (Throwable $e) {
            respond_error('No se pudo actualizar: ' . $e->getMessage(), '/weekly');
        }
    }

    public function destroy(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $taskId = (int) $id;
        $task = $this->plan->find($userId, $taskId);
        try {
            $this->plan->delete($userId, $taskId);
            $this->audit->log($userId, 'weekly.delete', 'weekly_task', $taskId);
            $date = (string) ($task['task_date'] ?? '');
            respond_saved('Tarea eliminada.', $this->redirectForDate($date));
        } catch (Throwable $e) {
            respond_error('No se pudo eliminar.', '/weekly');
        }
    }

    public function toggle(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $taskId = (int) $id;
        try {
            $status = input('status');
            $done = null;
            if ($status === 'completed') {
                $done = true;
            } elseif ($status === 'pending') {
                $done = false;
            }
            $result = $this->plan->toggle($userId, $taskId, $done);
            $this->audit->log($userId, 'weekly.toggle', 'weekly_task', $taskId, $result);

            if (wants_json_request()) {
                $task = $this->plan->find($userId, $taskId);
                $date = (string) ($task['task_date'] ?? now_local()->format('Y-m-d'));
                $dayTasks = $this->plan->listForDate($userId, $date);
                $total = count($dayTasks);
                $doneCount = 0;
                foreach ($dayTasks as $t) {
                    if ((int) $t['is_done'] === 1) {
                        $doneCount++;
                    }
                }
                json_response([
                    'ok' => true,
                    'task' => $result,
                    'day' => [
                        'date' => $date,
                        'total' => $total,
                        'done' => $doneCount,
                        'percent' => $total > 0 ? round(($doneCount / $total) * 100, 1) : 0.0,
                        'complete' => $total > 0 && $doneCount === $total,
                    ],
                ]);
            }

            $redirect = (string) (input('redirect') ?: '/weekly');
            if (!str_starts_with($redirect, '/')) {
                $redirect = '/weekly';
            }
            respond_saved('Tarea actualizada.', $redirect);
        } catch (Throwable $e) {
            if (wants_json_request()) {
                json_response(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            respond_error('No se pudo actualizar.', '/weekly');
        }
    }

    private function refFromRequest(): DateTimeImmutable
    {
        $week = input('week');
        if (is_string($week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
            return new DateTimeImmutable($week, now_local()->getTimezone());
        }
        return now_local();
    }

    private function redirectForDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '/weekly?week=' . $date;
        }
        return '/weekly';
    }
}
