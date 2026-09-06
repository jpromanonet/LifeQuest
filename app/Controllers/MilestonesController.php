<?php

declare(strict_types=1);

final class MilestonesController
{
    private MilestoneService $milestones;
    private AuditService $audit;

    public function __construct()
    {
        $this->milestones = new MilestoneService();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $today = now_local()->format('Y-m-d');
        $rows = $this->milestones->listAll($userId);

        $upcoming = [];
        $due = [];
        $done = [];
        foreach ($rows as $row) {
            if ((int) ($row['is_done'] ?? 0) === 1) {
                $done[] = $row;
                continue;
            }
            if ((string) $row['milestone_date'] <= $today) {
                $due[] = $row;
            } else {
                $upcoming[] = $row;
            }
        }

        view('milestones/index', [
            'title' => 'Hitos',
            'currentNav' => 'milestones',
            'upcoming' => $upcoming,
            'due' => $due,
            'done' => $done,
            'stats' => $this->milestones->stats($userId),
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
            $id = $this->milestones->create($userId, [
                'title' => input('title'),
                'milestone_date' => input('milestone_date'),
                'notes' => input('notes'),
            ]);
            $this->audit->log($userId, 'milestone.create', 'milestone', $id);
            respond_saved('Hito agregado.', '/milestones');
        } catch (Throwable $e) {
            respond_error(
                $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo crear el hito.',
                '/milestones'
            );
        }
    }

    public function update(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $milestoneId = (int) $id;
        try {
            $this->milestones->update($userId, $milestoneId, [
                'title' => input('title'),
                'milestone_date' => input('milestone_date'),
                'notes' => input('notes'),
            ]);
            $this->audit->log($userId, 'milestone.update', 'milestone', $milestoneId);
            respond_saved('Hito actualizado.', '/milestones');
        } catch (Throwable $e) {
            respond_error(
                $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo actualizar.',
                '/milestones'
            );
        }
    }

    public function complete(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $milestoneId = (int) $id;
        try {
            $this->milestones->markDone($userId, $milestoneId);
            $this->audit->log($userId, 'milestone.complete', 'milestone', $milestoneId);
            $back = (string) (input('redirect') ?: '/milestones');
            if (!str_starts_with($back, '/')) {
                $back = '/milestones';
            }
            respond_saved('Hito marcado como hecho.', $back);
        } catch (Throwable $e) {
            respond_error('No se pudo marcar el hito.', '/milestones');
        }
    }

    public function reopen(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $milestoneId = (int) $id;
        try {
            $this->milestones->markPending($userId, $milestoneId);
            $this->audit->log($userId, 'milestone.reopen', 'milestone', $milestoneId);
            respond_saved('Hito reabierto.', '/milestones');
        } catch (Throwable $e) {
            respond_error('No se pudo reabrir el hito.', '/milestones');
        }
    }

    public function destroy(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $milestoneId = (int) $id;
        try {
            $this->milestones->delete($userId, $milestoneId);
            $this->audit->log($userId, 'milestone.delete', 'milestone', $milestoneId);
            respond_saved('Hito eliminado.', '/milestones');
        } catch (Throwable $e) {
            respond_error('No se pudo eliminar.', '/milestones');
        }
    }
}
