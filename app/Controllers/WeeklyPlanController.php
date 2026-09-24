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
        $isCurrentWeek = $bounds['monday']->format('Y-m-d') === $currentBounds['monday']->format('Y-m-d');

        $today = now_local()->format('Y-m-d');
        $dayFilter = $this->dayFilterFromRequest($board, $isCurrentWeek ? $today : null);
        $visible = $this->visibleDays($board, $dayFilter, $isCurrentWeek ? $today : null);

        view('weekly/index', [
            'title' => 'Plan semanal',
            'currentNav' => 'weekly',
            'board' => $board,
            'visibleDays' => $visible,
            'dayFilter' => $dayFilter,
            'todayDate' => $today,
            'stats' => $stats,
            'monday' => $bounds['monday'],
            'sunday' => $bounds['sunday'],
            'weekParam' => $bounds['monday']->format('Y-m-d'),
            'prevMonday' => $prevMonday,
            'nextMonday' => $nextMonday,
            'isCurrentWeek' => $isCurrentWeek,
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
                'task_kind' => input('task_kind'),
                'notes' => input('notes'),
                'start_time' => input('start_time'),
                'estimated_minutes' => input('estimated_minutes'),
                'repeat_days' => input('repeat_days', []),
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
                'task_kind' => input('task_kind'),
                'notes' => input('notes'),
                'start_time' => input('start_time'),
                'estimated_minutes' => input('estimated_minutes'),
                'repeat_days' => input('repeat_days', []),
            ]);
            $this->audit->log($userId, 'weekly.update', 'weekly_task', $taskId);
            $back = $this->safeRedirect();
            if ($back !== '/weekly') {
                respond_saved('Tarea actualizada.', $back);
            }
            $date = (string) (input('task_date') ?: '');
            respond_saved('Tarea actualizada.', $this->redirectForDate($date));
        } catch (Throwable $e) {
            respond_error('No se pudo actualizar: ' . $e->getMessage(), '/weekly');
        }
    }

    public function reorder(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $raw = input('items') ?? input('order') ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        $items = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (is_string($row) && str_contains($row, ':')) {
                    [$id, $kind] = explode(':', $row, 2);
                    $items[] = ['id' => (int) $id, 'kind' => $kind];
                    continue;
                }
                if (is_array($row)) {
                    $items[] = [
                        'id' => (int) ($row['id'] ?? 0),
                        'kind' => (string) ($row['kind'] ?? 'personal'),
                    ];
                }
            }
        }
        try {
            $this->plan->reorder($userId, $items);
            $this->audit->log($userId, 'weekly.reorder', 'weekly_task', null, ['items' => $items]);
            if (wants_json_request()) {
                json_response(['ok' => true]);
            }
            respond_saved('Orden actualizado.', (string) (input('redirect') ?: '/weekly'));
        } catch (Throwable $e) {
            if (wants_json_request()) {
                json_response(['ok' => false, 'error' => 'No se pudo reordenar.'], 422);
            }
            respond_error('No se pudo reordenar.', '/weekly');
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
                $dayMins = WeeklyPlanService::minutesFromTasks($dayTasks);
                $week = $this->plan->weekStats($userId, new DateTimeImmutable($date, now_local()->getTimezone()));
                json_response([
                    'ok' => true,
                    'task' => $result,
                    'day' => [
                        'date' => $date,
                        'total' => $total,
                        'done' => $doneCount,
                        'percent' => $total > 0 ? round(($doneCount / $total) * 100, 1) : 0.0,
                        'complete' => $total > 0 && $doneCount === $total,
                        'hours' => hours_stats_payload($dayMins['done'], $dayMins['total']),
                    ],
                    'week' => [
                        'hours' => hours_stats_payload(
                            (int) $week['week_minutes_done'],
                            (int) $week['week_minutes_total']
                        ),
                        'today_hours' => hours_stats_payload(
                            (int) $week['today_minutes_done'],
                            (int) $week['today_minutes_total']
                        ),
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

    public function image(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $taskId = (int) $id;
        $back = $this->safeRedirect();

        try {
            if ((string) input('remove', '') === '1') {
                $old = $this->plan->setImage($userId, $taskId, null);
                $this->deleteUploadedFile($old);
                $this->audit->log($userId, 'weekly.image_remove', 'weekly_task', $taskId);
                respond_saved('Imagen quitada.', $back);
            }

            $file = $_FILES['image'] ?? null;
            $uploadError = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            if ($uploadError !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException($this->uploadErrorMessage($uploadError));
            }
            if ((int) $file['size'] > 5 * 1024 * 1024) {
                throw new InvalidArgumentException('La imagen no puede superar 5 MB.');
            }
            $tmp = (string) $file['tmp_name'];
            $info = is_file($tmp) ? @getimagesize($tmp) : false;
            $extByMime = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];
            $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
            if (!isset($extByMime[$mime])) {
                throw new InvalidArgumentException('Formato no soportado. Usá JPG, PNG, WEBP o GIF.');
            }

            $dir = $this->uploadsDir();
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('No se pudo crear la carpeta de subidas.');
            }
            if (!is_writable($dir)) {
                @chmod($dir, 0777);
            }
            if (!is_writable($dir)) {
                throw new RuntimeException('La carpeta de imágenes no tiene permiso de escritura.');
            }
            $name = 'task' . $taskId . '_' . bin2hex(random_bytes(6)) . '.' . $extByMime[$mime];
            $dest = $dir . DIRECTORY_SEPARATOR . $name;
            $saved = @move_uploaded_file($tmp, $dest);
            if (!$saved && is_uploaded_file($tmp)) {
                $saved = @copy($tmp, $dest);
            }
            if (!$saved || !is_file($dest)) {
                throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
            }

            $old = $this->plan->setImage($userId, $taskId, 'assets/uploads/tasks/' . $name);
            $this->deleteUploadedFile($old);
            $this->audit->log($userId, 'weekly.image_set', 'weekly_task', $taskId);
            respond_saved('Imagen agregada.', $back);
        } catch (Throwable $e) {
            respond_error(
                $e instanceof InvalidArgumentException || $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'No se pudo procesar la imagen.',
                $back
            );
        }
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen es demasiado grande (máximo ' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_PARTIAL => 'La subida se interrumpió. Probá de nuevo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar el archivo temporal.',
            UPLOAD_ERR_EXTENSION => 'Una extensión bloqueó la subida.',
            default => 'No se recibió ninguna imagen.',
        };
    }

    public function addStep(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $taskId = (int) $id;
        $back = $this->safeRedirect();

        try {
            $stepId = $this->plan->addStep($userId, $taskId, (string) input('title', ''));
            $this->audit->log($userId, 'weekly.step_add', 'weekly_task_step', $stepId);
            respond_saved('Paso agregado.', $back);
        } catch (Throwable $e) {
            respond_error(
                $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo agregar el paso.',
                $back
            );
        }
    }

    public function toggleStep(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $stepId = (int) $id;

        try {
            $result = $this->plan->toggleStep($userId, $stepId);
            $this->audit->log($userId, 'weekly.step_toggle', 'weekly_task_step', $stepId, $result);
            if (wants_json_request()) {
                json_response(['ok' => true] + $result);
            }
            respond_saved('Paso actualizado.', $this->safeRedirect());
        } catch (Throwable $e) {
            if (wants_json_request()) {
                json_response(['ok' => false, 'error' => 'No se pudo actualizar el paso.'], 422);
            }
            respond_error('No se pudo actualizar el paso.', $this->safeRedirect());
        }
    }

    public function deleteStep(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $stepId = (int) $id;

        try {
            $this->plan->deleteStep($userId, $stepId);
            $this->audit->log($userId, 'weekly.step_delete', 'weekly_task_step', $stepId);
            respond_saved('Paso eliminado.', $this->safeRedirect());
        } catch (Throwable $e) {
            respond_error('No se pudo eliminar el paso.', $this->safeRedirect());
        }
    }

    private function safeRedirect(): string
    {
        $r = (string) (input('redirect') ?: '/weekly');
        return str_starts_with($r, '/') ? $r : '/weekly';
    }

    private function uploadsDir(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tasks';
    }

    private function deleteUploadedFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '' || !str_starts_with($relativePath, 'assets/uploads/tasks/')) {
            return;
        }
        $file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($file)) {
            @unlink($file);
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
        $params = [];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $params['week'] = $date;
            $day = (string) (input('filter_day') ?: input('day') ?: '');
            if ($day === 'all' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                $params['day'] = $day;
            } else {
                $params['day'] = $date;
            }
            return '/weekly?' . http_build_query($params);
        }
        return '/weekly';
    }

    /**
     * @param list<array<string, mixed>> $board
     */
    private function dayFilterFromRequest(array $board, ?string $today): string
    {
        $day = (string) (input('day') ?: '');
        if ($day === 'all') {
            return 'all';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            foreach ($board as $row) {
                if ((string) ($row['date'] ?? '') === $day) {
                    return $day;
                }
            }
        }
        // Semana actual: por defecto el día de hoy. Otras semanas: toda la semana.
        if ($today !== null) {
            foreach ($board as $row) {
                if ((string) ($row['date'] ?? '') === $today) {
                    return $today;
                }
            }
        }
        return 'all';
    }

    /**
     * @param list<array<string, mixed>> $board
     * @return list<array<string, mixed>>
     */
    private function visibleDays(array $board, string $dayFilter, ?string $today = null): array
    {
        if ($dayFilter === 'all') {
            return $board;
        }

        foreach ($board as $row) {
            if ((string) ($row['date'] ?? '') === $dayFilter) {
                return [$row];
            }
        }

        return $board;
    }
}
