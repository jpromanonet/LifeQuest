<?php

declare(strict_types=1);

final class AnnualPlanService
{
    /** Canonical annual template — áreas directas del año. */
    public const TEMPLATE_AREA_KEYS = [
        'annual_educacion',
        'annual_trabajo',
        'annual_marca',
        'annual_finanzas',
        'annual_salud',
        'annual_proyectos',
    ];

    public function ensureYear(int $userId, int $year): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare(
                'SELECT id FROM annual_plans WHERE user_id = :user_id AND year = :year LIMIT 1'
            );
            $find->execute(['user_id' => $userId, 'year' => $year]);
            $planId = $find->fetchColumn();

            if ($planId === false) {
                $ins = $pdo->prepare(
                    'INSERT INTO annual_plans (user_id, year, title)
                     VALUES (:user_id, :year, :title)'
                );
                $ins->execute([
                    'user_id' => $userId,
                    'year' => $year,
                    'title' => 'Plan ' . $year,
                ]);
                $planId = (int) $pdo->lastInsertId();
            } else {
                $planId = (int) $planId;
            }

            $areasStmt = $pdo->prepare(
                'SELECT id, area_key FROM life_areas
                 WHERE user_id = :user_id AND deleted_at IS NULL AND scope = \'annual\''
            );
            $areasStmt->execute(['user_id' => $userId]);
            $areasByKey = [];
            foreach ($areasStmt->fetchAll() as $area) {
                $areasByKey[$area['area_key']] = (int) $area['id'];
            }

            $existingStmt = $pdo->prepare(
                'SELECT area_id FROM annual_sections WHERE annual_plan_id = :plan_id'
            );
            $existingStmt->execute(['plan_id' => $planId]);
            $existingAreas = array_map('intval', $existingStmt->fetchAll(PDO::FETCH_COLUMN));
            $existingSet = array_fill_keys($existingAreas, true);

            $insSection = $pdo->prepare(
                'INSERT INTO annual_sections (annual_plan_id, area_id, sort_order)
                 VALUES (:plan_id, :area_id, :sort_order)'
            );
            $updSection = $pdo->prepare(
                'UPDATE annual_sections SET sort_order = :sort_order
                 WHERE annual_plan_id = :plan_id AND area_id = :area_id'
            );

            $sort = 0;
            $keepIds = [];
            $orderedKeys = self::TEMPLATE_AREA_KEYS;
            foreach ($areasByKey as $key => $_id) {
                if (!in_array($key, $orderedKeys, true)) {
                    $orderedKeys[] = $key;
                }
            }
            foreach ($orderedKeys as $key) {
                if (!isset($areasByKey[$key])) {
                    continue;
                }
                $sort++;
                $areaId = $areasByKey[$key];
                $keepIds[] = $areaId;
                if (isset($existingSet[$areaId])) {
                    $updSection->execute([
                        'sort_order' => $sort,
                        'plan_id' => $planId,
                        'area_id' => $areaId,
                    ]);
                } else {
                    $insSection->execute([
                        'plan_id' => $planId,
                        'area_id' => $areaId,
                        'sort_order' => $sort,
                    ]);
                }
            }

            if ($keepIds !== []) {
                $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
                $del = $pdo->prepare(
                    "DELETE FROM annual_sections
                     WHERE annual_plan_id = ?
                       AND area_id NOT IN ($placeholders)"
                );
                $del->execute(array_merge([$planId], $keepIds));
            } else {
                $pdo->prepare('DELETE FROM annual_sections WHERE annual_plan_id = ?')->execute([$planId]);
            }

            $pdo->commit();
            return $planId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Elimina el plan de un año: borra el plan con sus secciones y archiva los
     * objetivos anuales de ese año. Los objetivos de horizonte y la biblioteca
     * no dependen del año, así que quedan intactos.
     *
     * @return array{goals:int}
     */
    public function deleteYear(int $userId, int $year): array
    {
        if ($year === (int) now_local()->format('Y')) {
            throw new InvalidArgumentException('El año en curso no se puede eliminar.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $goals = $pdo->prepare(
                'UPDATE goals SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
                 WHERE user_id = :uid AND period_year = :year
                   AND deleted_at IS NULL
                   AND (horizon IS NULL OR horizon <> \'largo_plazo\')'
            );
            $goals->execute(['uid' => $userId, 'year' => $year]);
            $removed = $goals->rowCount();

            $plan = $pdo->prepare('SELECT id FROM annual_plans WHERE user_id = :uid AND year = :year LIMIT 1');
            $plan->execute(['uid' => $userId, 'year' => $year]);
            $planId = $plan->fetchColumn();
            if ($planId !== false) {
                $pdo->prepare('DELETE FROM annual_sections WHERE annual_plan_id = :pid')
                    ->execute(['pid' => (int) $planId]);
                $pdo->prepare('DELETE FROM annual_plans WHERE id = :pid AND user_id = :uid')
                    ->execute(['pid' => (int) $planId, 'uid' => $userId]);
            }

            $pdo->commit();
            return ['goals' => $removed];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Años con plan creado, más el rango base y el año actual.
     *
     * @return list<int>
     */
    public function years(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT DISTINCT year FROM annual_plans WHERE user_id = :uid ORDER BY year ASC'
        );
        $stmt->execute(['uid' => $userId]);
        $years = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        // Los objetivos de horizonte no pertenecen a un año, no deben abrir un chip.
        $goalYears = Database::pdo()->prepare(
            'SELECT DISTINCT period_year FROM goals
             WHERE user_id = :uid AND deleted_at IS NULL AND period_year IS NOT NULL
               AND (horizon IS NULL OR horizon <> \'largo_plazo\')'
        );
        $goalYears->execute(['uid' => $userId]);
        $years = array_merge($years, array_map('intval', $goalYears->fetchAll(PDO::FETCH_COLUMN)));

        $years[] = (int) now_local()->format('Y');
        $years = array_values(array_unique(array_filter($years, static fn(int $y): bool => $y >= 2000 && $y <= 2100)));
        sort($years);

        return $years;
    }

    /** @return list<array<string, mixed>> */
    public function sectionsForYear(int $userId, int $year): array
    {
        $this->ensureYear($userId, $year);

        $stmt = Database::pdo()->prepare(
            'SELECT s.id AS section_id, s.sort_order, s.annual_plan_id,
                    la.id AS area_id, la.area_key, la.name AS area_name, la.color AS area_color,
                    la.icon, la.annual_goals_only, la.detail_system
             FROM annual_plans p
             INNER JOIN annual_sections s ON s.annual_plan_id = p.id
             INNER JOIN life_areas la ON la.id = s.area_id
             WHERE p.user_id = :user_id AND p.year = :year
             ORDER BY s.sort_order ASC, s.id ASC'
        );
        $stmt->execute(['user_id' => $userId, 'year' => $year]);
        return $stmt->fetchAll();
    }
}
