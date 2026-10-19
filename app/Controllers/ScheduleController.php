<?php

declare(strict_types=1);

final class ScheduleController
{
    private ScheduleService $schedule;
    private AuditService $audit;

    public function __construct()
    {
        $this->schedule = new ScheduleService();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        view('schedule/index', [
            'title' => 'Horario',
            'currentNav' => 'horario',
            'board' => $this->schedule->board($userId),
            'colors' => ScheduleService::COLORS,
            'dayLabels' => ScheduleService::DAY_LABELS,
            'dayShort' => ScheduleService::DAY_SHORT,
            'startSlots' => ScheduleService::slots(false),
            'endSlots' => ScheduleService::slots(true),
            'dayStart' => ScheduleService::DAY_START,
            'dayEnd' => ScheduleService::DAY_END,
            'snap' => ScheduleService::SNAP,
            'todayWeekday' => (int) now_local()->format('N'),
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        try {
            $block = $this->schedule->create($userId, $this->payload());
            $copies = $this->schedule->repeatOnDays($userId, (int) $block['id'], input('repeat_days', []));
            $this->audit->log($userId, 'schedule.create', 'schedule_block', (int) $block['id']);
            $this->respond($block, 'Bloque agregado.', $copies);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    public function update(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $blockId = (int) $id;
        try {
            $block = $this->schedule->update($userId, $blockId, $this->payload());
            $copies = $this->schedule->repeatOnDays($userId, $blockId, input('repeat_days', []));
            $this->audit->log($userId, 'schedule.update', 'schedule_block', $blockId);
            $this->respond($block, 'Bloque actualizado.', $copies);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    public function destroy(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $blockId = (int) $id;
        try {
            $this->schedule->delete($userId, $blockId);
            $this->audit->log($userId, 'schedule.delete', 'schedule_block', $blockId);
            if (wants_json_request()) {
                json_response(['ok' => true, 'id' => $blockId]);
            }
            respond_saved('Bloque eliminado.', '/horario');
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'title' => input('title'),
            'notes' => input('notes'),
            'weekday' => input('weekday'),
            'start_minute' => input('start_minute'),
            'end_minute' => input('end_minute'),
            'color' => input('color'),
        ];
    }

    /**
     * @param array<string, mixed> $block
     * @param list<array<string, mixed>> $copies
     */
    private function respond(array $block, string $message, array $copies = []): void
    {
        if (wants_json_request()) {
            json_response([
                'ok' => true,
                'message' => $message,
                'block' => $block,
                'blocks' => array_merge([$block], $copies),
            ]);
        }
        respond_saved($message, '/horario');
    }

    private function fail(Throwable $e): void
    {
        $message = $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'No se pudo guardar el bloque.';
        respond_error($message, '/horario');
    }
}
