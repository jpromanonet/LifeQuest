<?php

declare(strict_types=1);

final class ReviewsController
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM period_reviews
             WHERE user_id = :user_id AND deleted_at IS NULL
             ORDER BY period_start DESC, id DESC
             LIMIT 40'
        );
        $stmt->execute(['user_id' => $userId]);
        $reviews = $stmt->fetchAll();
        foreach ($reviews as &$review) {
            $answers = $review['answers_json'] ?? null;
            if (is_string($answers) && $answers !== '') {
                $decoded = json_decode($answers, true);
                $review['answers'] = is_array($decoded) ? $decoded : [];
            } else {
                $review['answers'] = [];
            }
        }
        unset($review);

        $now = now_local();
        $dow = (int) $now->format('N');
        $weekStart = $now->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
        $weekEnd = $now->modify('+' . (7 - $dow) . ' days')->format('Y-m-d');
        $monthStart = $now->format('Y-m-01');
        $monthEnd = $now->modify('last day of this month')->format('Y-m-d');

        view('reviews/index', [
            'title' => 'Revisiones',
            'currentNav' => 'reviews',
            'reviews' => $reviews,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'flashSuccess' => flash('success'),
            'flashError' => flash('error'),
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();

        $type = (string) input('review_type', 'weekly');
        if (!in_array($type, ['weekly', 'monthly', 'quarterly', 'annual'], true)) {
            $type = 'weekly';
        }

        $periodStart = (string) input('period_start', '');
        $periodEnd = (string) input('period_end', '');
        $status = (string) input('status', 'completed');
        if (!in_array($status, ['draft', 'completed'], true)) {
            $status = 'completed';
        }

        $answers = [
            'went_well' => trim((string) input('went_well', '')),
            'improve' => trim((string) input('improve', '')),
            'focus_next' => trim((string) input('focus_next', '')),
            'energy' => input('energy'),
            'notes' => trim((string) input('notes', '')),
        ];

        if ($periodStart === '' || $periodEnd === '') {
            flash('error', 'Indicá el período de la revisión.');
            redirect('/reviews');
        }

        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO period_reviews (
                    user_id, review_type, period_start, period_end, answers_json, status, completed_at
                 ) VALUES (
                    :user_id, :review_type, :period_start, :period_end, :answers_json, :status, :completed_at
                 )'
            );
            $stmt->execute([
                'user_id' => $userId,
                'review_type' => $type,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'answers_json' => json_encode($answers, JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'completed_at' => $status === 'completed' ? gmdate('Y-m-d H:i:s') : null,
            ]);
            $id = (int) Database::pdo()->lastInsertId();
            $this->audit->log($userId, 'review.create', 'period_review', $id, ['type' => $type]);
            flash('success', 'Revisión guardada.');
        } catch (Throwable $e) {
            flash('error', 'No se pudo guardar la revisión.');
        }

        redirect('/reviews');
    }
}
