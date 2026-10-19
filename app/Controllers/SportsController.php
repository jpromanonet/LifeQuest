<?php

declare(strict_types=1);

final class SportsController
{
    private SportService $sports;

    public function __construct()
    {
        $this->sports = new SportService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $date = $this->selectedDate();
        view('sports/index', [
            'title' => 'Entrenamiento',
            'currentNav' => 'sports',
            'board' => $this->sports->board(Auth::id(), $date),
            'dayShort' => SportService::DAY_SHORT,
        ]);
    }

    public function saveBody(): void
    {
        $this->act(function (int $userId, string $date): void {
            $this->sports->saveBody($userId, input('height_cm'), input('weight_kg'), $date);
        }, 'Medidas guardadas.');
    }

    public function setRoutine(): void
    {
        $this->act(function (int $userId, string $date): void {
            $this->sports->setRoutine($userId, (string) input('has_routine') === '1');
        }, 'Rutina actualizada.');
    }

    public function storeExercise(): void
    {
        $this->act(function (int $userId, string $date): void {
            $this->sports->addExercise($userId, (string) input('name'), input('reps'));
        }, 'Ejercicio agregado.');
    }

    public function updateExerciseReps(string $id): void
    {
        $this->act(function (int $userId, string $date) use ($id): void {
            $this->sports->updateExerciseReps($userId, (int) $id, input('reps'));
        }, 'Cantidad actualizada.');
    }

    public function renameExercise(string $id): void
    {
        $this->act(function (int $userId, string $date) use ($id): void {
            $this->sports->renameExercise($userId, (int) $id, (string) input('name'));
        }, 'Ejercicio actualizado.');
    }

    public function reorderExercises(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $raw = input('order', '');
        $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
        try {
            $this->sports->reorderExercises(Auth::id(), array_map('intval', $ids));
            if (wants_json_request()) {
                json_response(['ok' => true]);
            }
            respond_saved('Orden actualizado.', $this->returnPath($this->selectedDate()));
        } catch (Throwable $e) {
            if (wants_json_request()) {
                json_response(['ok' => false], 422);
            }
            respond_error('No se pudo reordenar.', $this->returnPath($this->selectedDate()));
        }
    }

    public function toggleExercise(string $id): void
    {
        $this->act(function (int $userId, string $date) use ($id): void {
            $this->sports->toggleExercise($userId, (int) $id, $date);
        }, 'Listo.');
    }

    public function destroyExercise(string $id): void
    {
        $this->act(function (int $userId, string $date) use ($id): void {
            $this->sports->deleteExercise($userId, (int) $id);
        }, 'Ejercicio eliminado.');
    }

    public function store(): void
    {
        $this->act(function (int $userId, string $date): void {
            $this->sports->createDiscipline($userId, [
                'name' => input('name'),
                'kind' => input('kind'),
                'target_amount' => input('target_amount'),
                'weekdays' => input('weekdays', []),
            ]);
        }, 'Disciplina agregada.');
    }

    public function log(string $id): void
    {
        $this->act(function (int $userId, string $date) use ($id): void {
            $this->sports->logDiscipline($userId, (int) $id, $date, input('quantity'), input('attended'));
        }, 'Día actualizado.');
    }

    public function destroy(string $id): void
    {
        $this->act(function (int $userId, string $date) use ($id): void {
            $this->sports->deleteDiscipline($userId, (int) $id);
        }, 'Disciplina eliminada.');
    }

    private function selectedDate(): string
    {
        $today = now_local()->format('Y-m-d');
        $raw = trim((string) input('date', $today));
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if (!$dt || $dt->format('Y-m-d') !== $raw || $dt->format('Y-m-d') > $today) {
            return $today;
        }
        return $raw;
    }

    /** @param callable(int, string): void $callback */
    private function act(callable $callback, string $message): void
    {
        Auth::requireLogin();
        verify_csrf();
        $date = $this->selectedDate();
        try {
            $callback(Auth::id(), $date);
            respond_saved($message, $this->returnPath($date));
        } catch (Throwable $e) {
            $text = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'No se pudo guardar.';
            respond_error($text, $this->returnPath($date));
        }
    }

    private function returnPath(string $date): string
    {
        $redirect = (string) input('redirect', '');
        if ($redirect === '/today' || $redirect === '/metrics') {
            return $redirect;
        }
        return '/deportes?date=' . $date;
    }
}
