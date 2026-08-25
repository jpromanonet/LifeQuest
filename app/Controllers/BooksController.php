<?php

declare(strict_types=1);

final class BooksController
{
    private BookService $books;
    private AuditService $audit;

    public function __construct()
    {
        $this->books = new BookService();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $yearParam = input('year');
        $allYears = is_string($yearParam) && strtolower($yearParam) === 'all';
        $year = $allYears ? null : (int) ($yearParam ?: now_local()->format('Y'));
        $currentYear = (int) now_local()->format('Y');

        if ($year !== null) {
            $this->books->ensureAnnualGoal($userId, $year);
        }

        $list = $this->books->list($userId, $year);
        $stats = $this->books->statsForYear($userId, $year);

        $availableYears = (new AnnualPlanService())->years($userId);
        foreach ($this->books->yearsWithBooks($userId) as $y) {
            if (!in_array($y, $availableYears, true)) {
                $availableYears[] = $y;
            }
        }
        rsort($availableYears, SORT_NUMERIC);
        if ($availableYears === []) {
            $availableYears = [$currentYear];
        }

        view('books/index', [
            'title' => 'Libros',
            'currentNav' => 'goals',
            'year' => $year ?? $currentYear,
            'allYears' => $allYears,
            'availableYears' => $availableYears,
            'books' => $list,
            'stats' => $stats,
            'target' => $year !== null ? $this->books->annualTarget($userId, $year) : null,
            'monthLabels' => MonthProgress::MONTH_LABELS,
            'flashSuccess' => flash('success'),
            'flashError' => flash('error'),
        ]);
    }

    public function target(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $year = (int) (input('year') ?: now_local()->format('Y'));

        // El stepper manda un paso relativo; el campo numérico, el valor exacto.
        $step = (int) input('step', 0);
        $target = $step !== 0
            ? $this->books->annualTarget($userId, $year) + $step
            : (int) (input('target') ?: BookService::DEFAULT_TARGET);
        $target = max(1, $target);

        try {
            $this->books->setAnnualTarget($userId, $year, $target);
            $this->audit->log($userId, 'book.target', 'goal', null, ['year' => $year, 'target' => $target]);
            respond_saved('Meta del año actualizada.', '/books?year=' . $year);
        } catch (Throwable $e) {
            respond_error('No se pudo actualizar la meta.', '/books?year=' . $year);
        }
    }

    public function store(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        try {
            $data = $this->payload();
            $id = $this->books->create($userId, $data);
            $this->audit->log($userId, 'book.create', 'book', $id);
            $year = (int) ($data['year'] ?? date('Y'));
            $this->books->ensureAnnualGoal($userId, $year);
            respond_saved('Libro agregado.', '/books?year=' . $year);
        } catch (Throwable $e) {
            respond_error('No se pudo crear el libro.', '/books');
        }
    }

    public function update(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $bookId = (int) $id;
        try {
            $data = $this->payload();
            $this->books->update($userId, $bookId, $data);
            $this->audit->log($userId, 'book.update', 'book', $bookId);
            $year = (int) ($data['year'] ?? date('Y'));
            $this->books->ensureAnnualGoal($userId, $year);
            respond_saved('Libro actualizado.', '/books?year=' . $year);
        } catch (Throwable $e) {
            respond_error('No se pudo actualizar.', '/books');
        }
    }

    public function destroy(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        try {
            $book = $this->books->find($userId, (int) $id);
            $year = (int) ($book['year_num'] ?? date('Y'));
            $this->books->softDelete($userId, (int) $id);
            $this->books->ensureAnnualGoal($userId, $year);
            respond_saved('Libro eliminado.', '/books?year=' . $year);
        } catch (Throwable $e) {
            respond_error('No se pudo eliminar.', '/books');
        }
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'title' => input('title'),
            'year' => input('year') ?: now_local()->format('Y'),
            'planned_month' => input('planned_month'),
            'finished_at' => input('finished_at'),
            'pages' => input('pages'),
            'pages_read' => input('pages_read') ?: 0,
            'status' => input('status') ?: 'planned',
            'goodreads_url' => input('goodreads_url'),
            'notes' => input('notes'),
        ];
    }
}
