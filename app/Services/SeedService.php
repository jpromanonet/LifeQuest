<?php

declare(strict_types=1);

/**
 * Siembra los datos de ejemplo de SeedManifest en una instalación nueva.
 * Es idempotente: se apoya en goal_key / habit_key / area_key para no duplicar.
 */
final class SeedService
{
    /** @return array{user:int,areas:int,annual_plans:int,goals:int,habits:int} */
    public static function run(PDO $pdo): array
    {
        $manifest = SeedManifest::data();
        $report = [
            'user' => 0,
            'areas' => 0,
            'annual_plans' => 0,
            'goals' => 0,
            'habits' => 0,
        ];

        $pdo->beginTransaction();
        $userId = 0;
        try {
            $userId = self::upsertUser($pdo, $manifest['user']);
            $report['user'] = 1;

            $areaIds = self::seedAreas($pdo, $userId, $manifest['life_areas']);
            $report['areas'] = count($areaIds);

            $currentYear = (int) date('Y');
            $years = [
                'previous' => $currentYear - 1,
                'current' => $currentYear,
                'next' => $currentYear + 1,
            ];

            foreach ($years as $year) {
                self::ensureAnnualPlan($pdo, $userId, $year, $areaIds, $manifest['annual_template_sections']);
                $report['annual_plans']++;
            }

            $report['goals'] += self::seedLongTerm($pdo, $userId, $areaIds, $manifest['long_term_goals']);

            foreach ($manifest['annual_goals'] as $slot => $goals) {
                if (!isset($years[$slot])) {
                    continue;
                }
                $report['goals'] += self::seedAnnualGoals($pdo, $userId, $areaIds, $years[$slot], $goals);
            }

            $report['habits'] = self::seedHabits($pdo, $userId, $manifest['habits']);

            self::linkPatrium($pdo, $userId);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Fuera de la transacción: hábitos imborrables de sistema (agua, frutas, etc.).
        if ($userId > 0 && class_exists(HabitService::class)) {
            (new HabitService())->ensureSystemHabits($userId);
        }

        return $report;
    }

    private static function upsertUser(PDO $pdo, array $user): int
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE user_key = :k LIMIT 1');
        $stmt->execute(['k' => $user['key']]);
        $existing = $stmt->fetch();
        if ($existing) {
            return (int) $existing['id'];
        }

        $ins = $pdo->prepare(
            'INSERT INTO users (user_key, name, email, password_hash, locale, timezone, theme, is_active)
             VALUES (:key, :name, :email, :hash, :locale, :tz, \'light\', 1)'
        );
        $ins->execute([
            'key' => $user['key'],
            'name' => $user['name'],
            'email' => $user['email'],
            'hash' => password_hash($user['password'], PASSWORD_DEFAULT),
            'locale' => $user['locale'],
            'tz' => $user['timezone'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** @return array<string,int> */
    private static function seedAreas(PDO $pdo, int $userId, array $areas): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO life_areas (user_id, area_key, name, color, icon, sort_order, scope, annual_goals_only, detail_system)
             VALUES (:uid, :key, :name, :color, :icon, :sort, :scope, :ago, :ds)
             ON DUPLICATE KEY UPDATE icon = VALUES(icon),
               sort_order = VALUES(sort_order), scope = VALUES(scope),
               annual_goals_only = VALUES(annual_goals_only), detail_system = VALUES(detail_system)'
        );
        foreach ($areas as $area) {
            $stmt->execute([
                'uid' => $userId,
                'key' => $area['key'],
                'name' => $area['name'],
                'color' => $area['color'],
                'icon' => $area['icon'],
                'sort' => $area['sort'],
                'scope' => $area['scope'] ?? 'annual',
                'ago' => !empty($area['annual_goals_only']) ? 1 : 0,
                'ds' => $area['detail_system'] ?? null,
            ]);
        }

        $map = [];
        $q = $pdo->prepare('SELECT id, area_key FROM life_areas WHERE user_id = :uid AND deleted_at IS NULL');
        $q->execute(['uid' => $userId]);
        foreach ($q->fetchAll() as $row) {
            $map[$row['area_key']] = (int) $row['id'];
        }
        return $map;
    }

    private static function ensureAnnualPlan(PDO $pdo, int $userId, int $year, array $areaIds, array $sectionKeys): void
    {
        $stmt = $pdo->prepare('SELECT id FROM annual_plans WHERE user_id = :uid AND year = :y LIMIT 1');
        $stmt->execute(['uid' => $userId, 'y' => $year]);
        $row = $stmt->fetch();
        if ($row) {
            $planId = (int) $row['id'];
        } else {
            $ins = $pdo->prepare('INSERT INTO annual_plans (user_id, year, title) VALUES (:uid, :y, :t)');
            $ins->execute(['uid' => $userId, 'y' => $year, 't' => 'Plan ' . $year]);
            $planId = (int) $pdo->lastInsertId();
        }

        $sec = $pdo->prepare(
            'INSERT IGNORE INTO annual_sections (annual_plan_id, area_id, sort_order)
             VALUES (:pid, :aid, :sort)'
        );
        $sort = 1;
        foreach ($sectionKeys as $key) {
            if (!isset($areaIds[$key])) {
                continue;
            }
            $sec->execute(['pid' => $planId, 'aid' => $areaIds[$key], 'sort' => $sort++]);
        }
    }

    private static function seedLongTerm(PDO $pdo, int $userId, array $areaIds, array $goals): int
    {
        $count = 0;
        foreach ($goals as $g) {
            $done = ($g['status'] ?? '') === 'completed';
            self::upsertGoal($pdo, $userId, [
                'goal_key' => $g['key'],
                'title' => $g['title'],
                'area_id' => $areaIds[$g['area']] ?? null,
                'horizon' => 'largo_plazo',
                'period_year' => null,
                'status' => $g['status'] ?? 'planned',
                'progress_mode' => 'binary',
                'progress_percent' => $done ? 100 : 0,
                'completed_at' => $done ? date('Y-m-d H:i:s') : null,
                'source_system' => 'lifequest',
            ]);
            $count++;
        }
        return $count;
    }

    private static function seedAnnualGoals(PDO $pdo, int $userId, array $areaIds, int $year, array $goals): int
    {
        $count = 0;
        foreach ($goals as $g) {
            $done = ($g['status'] ?? '') === 'completed';
            $mode = $g['progress_mode'] ?? 'months';
            $target = $g['target_value'] ?? null;
            $current = $g['current_value'] ?? null;

            $percent = 0.0;
            if ($done) {
                $percent = 100.0;
            } elseif ($mode === 'quantity' && $target > 0) {
                $percent = round(min(100, ((float) $current / (float) $target) * 100), 2);
            }

            self::upsertGoal($pdo, $userId, [
                'goal_key' => $g['key'],
                'title' => $g['title'],
                'area_id' => $areaIds[$g['area']] ?? null,
                'horizon' => 'anual',
                'period_year' => $year,
                'status' => $g['status'] ?? 'planned',
                'progress_mode' => $mode,
                'progress_percent' => $percent,
                'target_value' => $target,
                'current_value' => $current,
                'unit' => $g['unit'] ?? null,
                'completed_at' => $done ? date('Y-m-d H:i:s') : null,
                'source_system' => 'lifequest',
            ]);
            $count++;
        }
        return $count;
    }

    private static function seedHabits(PDO $pdo, int $userId, array $habits): int
    {
        $count = 0;
        foreach ($habits as $h) {
            $habitId = self::upsertHabit($pdo, $userId, [
                'habit_key' => $h['key'],
                'display_number' => $h['number'] ?? null,
                'name' => $h['name'],
                'area_id' => null,
                'frequency_type' => $h['frequency'] ?? 'daily',
                'target_per_period' => $h['target'] ?? 1,
                'unit' => $h['unit'] ?? 'vez',
                'preferred_time' => $h['preferred_time'] ?? 'anytime',
                'active' => 1,
            ]);
            if (($h['frequency'] ?? '') === 'weekdays' && !empty($h['days'])) {
                self::syncScheduleDays($pdo, $habitId, $h['days']);
            }
            $count++;
        }
        return $count;
    }

    private static function linkPatrium(PDO $pdo, int $userId): void
    {
        $url = (string) app_config('patrium_url', '');
        if ($url === '') {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO external_links (user_id, system_name, title, url, notes)
             SELECT :uid, \'Patrium\', \'Patrium — finanzas\', :url, \'Gestión financiera detallada\'
             FROM DUAL
             WHERE NOT EXISTS (
               SELECT 1 FROM external_links WHERE user_id = :uid2 AND system_name = \'Patrium\' AND deleted_at IS NULL
             )'
        );
        $stmt->execute(['uid' => $userId, 'uid2' => $userId, 'url' => $url]);
    }

    private static function upsertGoal(PDO $pdo, int $userId, array $data): int
    {
        $find = $pdo->prepare('SELECT id FROM goals WHERE user_id = :uid AND goal_key = :k LIMIT 1');
        $find->execute(['uid' => $userId, 'k' => $data['goal_key']]);
        $existing = $find->fetch();

        $fields = [
            'title', 'description', 'goal_type', 'area_id', 'impact_area_id', 'parent_goal_id', 'series_id',
            'horizon', 'period_year', 'period_quarter', 'period_month', 'status', 'priority', 'progress_mode',
            'progress_percent', 'target_value', 'current_value', 'unit', 'start_date', 'due_date',
            'success_criteria', 'motivation', 'next_action', 'external_system', 'external_url',
            'source_system', 'source_page', 'source_text', 'source_checked', 'completed_at',
        ];

        if ($existing) {
            $sets = [];
            $params = ['id' => (int) $existing['id']];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $sets[] = "$f = :$f";
                    $params[$f] = $data[$f];
                }
            }
            if ($sets) {
                $pdo->prepare('UPDATE goals SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
            }
            return (int) $existing['id'];
        }

        $cols = ['user_id', 'goal_key'];
        $vals = [':uid', ':goal_key'];
        $params = ['uid' => $userId, 'goal_key' => $data['goal_key']];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $cols[] = $f;
                $vals[] = ':' . $f;
                $params[$f] = $data[$f];
            }
        }
        $sql = 'INSERT INTO goals (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
        $pdo->prepare($sql)->execute($params);
        return (int) $pdo->lastInsertId();
    }

    private static function upsertHabit(PDO $pdo, int $userId, array $data): int
    {
        $find = $pdo->prepare('SELECT id FROM habits WHERE user_id = :uid AND habit_key = :k LIMIT 1');
        $find->execute(['uid' => $userId, 'k' => $data['habit_key']]);
        $existing = $find->fetch();

        $fields = [
            'display_number', 'name', 'description', 'area_id', 'frequency_type', 'target_per_period',
            'unit', 'minimum_value', 'preferred_time', 'start_date', 'end_date', 'active',
            'linked_goal_id', 'notes', 'archived_at',
        ];

        if ($existing) {
            $sets = [];
            $params = ['id' => (int) $existing['id']];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $sets[] = "$f = :$f";
                    $params[$f] = $data[$f];
                }
            }
            if ($sets) {
                $pdo->prepare('UPDATE habits SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
            }
            return (int) $existing['id'];
        }

        $cols = ['user_id', 'habit_key'];
        $vals = [':uid', ':habit_key'];
        $params = ['uid' => $userId, 'habit_key' => $data['habit_key']];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $cols[] = $f;
                $vals[] = ':' . $f;
                $params[$f] = $data[$f];
            }
        }
        $sql = 'INSERT INTO habits (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
        $pdo->prepare($sql)->execute($params);
        return (int) $pdo->lastInsertId();
    }

    private static function syncScheduleDays(PDO $pdo, int $habitId, array $days): void
    {
        $pdo->prepare('DELETE FROM habit_schedule_days WHERE habit_id = :id')->execute(['id' => $habitId]);
        $ins = $pdo->prepare('INSERT INTO habit_schedule_days (habit_id, weekday) VALUES (:hid, :d)');
        foreach ($days as $d) {
            $ins->execute(['hid' => $habitId, 'd' => (int) $d]);
        }
    }
}
