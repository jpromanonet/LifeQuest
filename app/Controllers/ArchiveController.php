<?php

declare(strict_types=1);

final class ArchiveController
{
    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $currentYear = (int) now_local()->format('Y');
        $filterYear = input('year');
        $filterYear = $filterYear !== null && $filterYear !== '' ? (int) $filterYear : null;

        $stmt = Database::pdo()->prepare(
            'SELECT g.*, la.name AS area_name, la.color AS area_color
             FROM goals g
             LEFT JOIN life_areas la ON la.id = g.area_id AND la.user_id = g.user_id
             WHERE g.user_id = :user_id
               AND g.deleted_at IS NULL
               AND (
                    g.status IN (\'completed\', \'archived\')
                    OR (g.period_year IS NOT NULL AND g.period_year < :year)
               )
             ORDER BY
                COALESCE(g.period_year, 0) DESC,
                g.completed_at DESC,
                g.id DESC'
        );
        $stmt->execute(['user_id' => $userId, 'year' => $currentYear]);
        $goals = $stmt->fetchAll();

        $years = [];
        $grouped = [];
        foreach ($goals as $goal) {
            $y = $goal['period_year'] !== null ? (int) $goal['period_year'] : 0;
            $years[$y] = true;
            if ($filterYear !== null && $y !== $filterYear) {
                continue;
            }
            $grouped[$y][] = $goal;
        }
        krsort($years);
        krsort($grouped);

        view('archive/index', [
            'title' => 'Archivo',
            'currentNav' => 'archive',
            'grouped' => $grouped,
            'availableYears' => array_keys($years),
            'filterYear' => $filterYear,
            'currentYear' => $currentYear,
        ]);
    }
}
