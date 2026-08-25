<?php

declare(strict_types=1);

final class AreaService
{
    public const ANNUAL_KEYS = [
        'annual_educacion',
        'annual_trabajo',
        'annual_marca',
        'annual_finanzas',
        'annual_salud',
        'annual_proyectos',
    ];

    public const HORIZON_KEYS = [
        'hz_laborales',
        'hz_salud',
        'hz_financieros',
        'hz_patrimonial',
        'hz_academicos',
        'hz_hobbies',
    ];

    /** Old annual keys → new annual keys */
    public const LEGACY_ANNUAL_MAP = [
        'career_it' => 'annual_trabajo',
        'management' => 'annual_trabajo',
        'entrepreneurship' => 'annual_trabajo',
        'projects' => 'annual_proyectos',
        'writing' => 'annual_marca',
        'home' => 'annual_marca',
        'experiences' => 'annual_marca',
        'education' => 'annual_educacion',
        'languages' => 'annual_educacion',
        'health' => 'annual_salud',
        'finance_external' => 'annual_finanzas',
    ];

    /** @return list<array<string, mixed>> */
    public function listByScope(int $userId, string $scope): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM life_areas
             WHERE user_id = :user_id AND deleted_at IS NULL AND scope = :scope
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['user_id' => $userId, 'scope' => $scope]);
        return $stmt->fetchAll();
    }

    public function rename(int $userId, int $areaId, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre no puede estar vacío');
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE life_areas
             SET name = :name
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'name' => $name,
            'id' => $areaId,
            'user_id' => $userId,
        ]);
        if ($stmt->rowCount() === 0) {
            $check = Database::pdo()->prepare(
                'SELECT id FROM life_areas WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL LIMIT 1'
            );
            $check->execute(['id' => $areaId, 'user_id' => $userId]);
            if (!$check->fetch()) {
                throw new RuntimeException('Área no encontrada');
            }
        }
    }

    public function recolor(int $userId, int $areaId, string $color): void
    {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return;
        }

        Database::pdo()->prepare(
            'UPDATE life_areas SET color = :color
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        )->execute([
            'color' => $color,
            'id' => $areaId,
            'user_id' => $userId,
        ]);
    }

    public function create(int $userId, string $scope, string $name, ?string $color = null): int
    {
        if (!in_array($scope, ['annual', 'horizon'], true)) {
            throw new InvalidArgumentException('Scope inválido');
        }
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre no puede estar vacío');
        }
        $color = $color && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#7C83E1';

        $pdo = Database::pdo();
        $max = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) FROM life_areas
             WHERE user_id = :uid AND scope = :scope AND deleted_at IS NULL'
        );
        $max->execute(['uid' => $userId, 'scope' => $scope]);
        $sort = (int) $max->fetchColumn() + 1;
        $key = 'custom_' . $scope . '_' . bin2hex(random_bytes(4));

        $stmt = $pdo->prepare(
            'INSERT INTO life_areas (user_id, area_key, name, color, icon, sort_order, scope)
             VALUES (:uid, :key, :name, :color, :icon, :sort, :scope)'
        );
        $stmt->execute([
            'uid' => $userId,
            'key' => $key,
            'name' => $name,
            'color' => $color,
            'icon' => 'circle',
            'sort' => $sort,
            'scope' => $scope,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function softDelete(int $userId, int $areaId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE life_areas SET deleted_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $areaId, 'uid' => $userId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Área no encontrada');
        }
    }

    /**
     * Ensure the 6 annual + 6 horizon areas exist, remap legacy annual goals/habits,
     * soft-delete obsolete annual areas, refresh annual sections.
     */
    public function ensureCanonicalAreas(int $userId): void
    {
        $pdo = Database::pdo();
        $annualDefs = [
            ['key' => 'annual_educacion', 'name' => 'Educación', 'color' => '#0891B2', 'icon' => 'graduation-cap', 'sort' => 1],
            ['key' => 'annual_trabajo', 'name' => 'Trabajo', 'color' => '#4F46E5', 'icon' => 'briefcase', 'sort' => 2],
            ['key' => 'annual_marca', 'name' => 'Marca personal', 'color' => '#DB2777', 'icon' => 'pen', 'sort' => 3],
            ['key' => 'annual_finanzas', 'name' => 'Finanzas', 'color' => '#64748B', 'icon' => 'wallet', 'sort' => 4, 'annual_goals_only' => true, 'detail_system' => 'Patrium'],
            ['key' => 'annual_salud', 'name' => 'Salud', 'color' => '#16A34A', 'icon' => 'heart-pulse', 'sort' => 5],
            ['key' => 'annual_proyectos', 'name' => 'Proyectos', 'color' => '#7C83E1', 'icon' => 'folder', 'sort' => 6],
        ];
        $horizonDefs = [
            ['key' => 'hz_laborales', 'name' => 'Laborales', 'color' => '#4F46E5', 'icon' => 'briefcase', 'sort' => 1],
            ['key' => 'hz_salud', 'name' => 'Salud', 'color' => '#16A34A', 'icon' => 'heart-pulse', 'sort' => 2],
            ['key' => 'hz_financieros', 'name' => 'Financieros', 'color' => '#0891B2', 'icon' => 'wallet', 'sort' => 3],
            ['key' => 'hz_patrimonial', 'name' => 'Patrimonial', 'color' => '#CA8A04', 'icon' => 'landmark', 'sort' => 4],
            ['key' => 'hz_academicos', 'name' => 'Académicos', 'color' => '#7C3AED', 'icon' => 'graduation-cap', 'sort' => 5],
            ['key' => 'hz_hobbies', 'name' => 'Hobbies', 'color' => '#EA580C', 'icon' => 'compass', 'sort' => 6],
        ];

        // El nombre y el color son editables por el usuario: solo se siembran al crear
        // el área, nunca se pisan en el upsert.
        $upsert = $pdo->prepare(
            'INSERT INTO life_areas (user_id, area_key, name, color, icon, sort_order, scope, annual_goals_only, detail_system)
             VALUES (:uid, :key, :name, :color, :icon, :sort, :scope, :ago, :ds)
             ON DUPLICATE KEY UPDATE
               icon = VALUES(icon),
               sort_order = VALUES(sort_order),
               scope = VALUES(scope),
               annual_goals_only = VALUES(annual_goals_only),
               detail_system = VALUES(detail_system),
               deleted_at = NULL'
        );

        foreach ($annualDefs as $a) {
            $upsert->execute([
                'uid' => $userId,
                'key' => $a['key'],
                'name' => $a['name'],
                'color' => $a['color'],
                'icon' => $a['icon'],
                'sort' => $a['sort'],
                'scope' => 'annual',
                'ago' => !empty($a['annual_goals_only']) ? 1 : 0,
                'ds' => $a['detail_system'] ?? null,
            ]);
        }
        foreach ($horizonDefs as $a) {
            $upsert->execute([
                'uid' => $userId,
                'key' => $a['key'],
                'name' => $a['name'],
                'color' => $a['color'],
                'icon' => $a['icon'],
                'sort' => $a['sort'],
                'scope' => 'horizon',
                'ago' => 0,
                'ds' => null,
            ]);
        }

        // Load id map
        $mapStmt = $pdo->prepare(
            'SELECT id, area_key FROM life_areas WHERE user_id = :uid AND deleted_at IS NULL'
        );
        $mapStmt->execute(['uid' => $userId]);
        $ids = [];
        foreach ($mapStmt->fetchAll() as $row) {
            $ids[$row['area_key']] = (int) $row['id'];
        }

        // Remap goals/habits from legacy annual areas
        $updGoal = $pdo->prepare(
            'UPDATE goals SET area_id = :new_id
             WHERE user_id = :uid AND area_id = :old_id AND deleted_at IS NULL'
        );
        $updImpact = $pdo->prepare(
            'UPDATE goals SET impact_area_id = :new_id
             WHERE user_id = :uid AND impact_area_id = :old_id AND deleted_at IS NULL'
        );
        $updHabit = $pdo->prepare(
            'UPDATE habits SET area_id = :new_id
             WHERE user_id = :uid AND area_id = :old_id AND deleted_at IS NULL'
        );

        foreach (self::LEGACY_ANNUAL_MAP as $oldKey => $newKey) {
            if (!isset($ids[$oldKey], $ids[$newKey])) {
                continue;
            }
            $oldId = $ids[$oldKey];
            $newId = $ids[$newKey];
            if ($oldId === $newId) {
                continue;
            }
            $updGoal->execute(['new_id' => $newId, 'uid' => $userId, 'old_id' => $oldId]);
            $updImpact->execute(['new_id' => $newId, 'uid' => $userId, 'old_id' => $oldId]);
            $updHabit->execute(['new_id' => $newId, 'uid' => $userId, 'old_id' => $oldId]);
        }

        // Soft-delete obsolete annual areas (legacy keys)
        $legacyKeys = array_keys(self::LEGACY_ANNUAL_MAP);
        if ($legacyKeys !== []) {
            $placeholders = implode(',', array_fill(0, count($legacyKeys), '?'));
            $sql = "UPDATE life_areas
                    SET deleted_at = UTC_TIMESTAMP()
                    WHERE user_id = ?
                      AND scope = 'annual'
                      AND area_key IN ($placeholders)
                      AND deleted_at IS NULL";
            $params = array_merge([$userId], $legacyKeys);
            $pdo->prepare($sql)->execute($params);
        }
    }
}
