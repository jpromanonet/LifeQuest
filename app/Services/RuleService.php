<?php

declare(strict_types=1);

/**
 * Reglas propias: categorías con reglas numeradas.
 * No están atadas a años, semanas ni días; tampoco se tildan.
 */
final class RuleService
{
    public function ensureTables(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $pdo = Database::pdo();

        $exists = $pdo->query("SHOW TABLES LIKE 'rule_categories'")->fetch();
        if (!$exists) {
            $pdo->exec(
                "CREATE TABLE rule_categories (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  user_id BIGINT UNSIGNED NOT NULL,
                  name VARCHAR(160) NOT NULL,
                  sort_order INT NOT NULL DEFAULT 0,
                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  deleted_at DATETIME NULL,
                  KEY idx_rule_categories_user (user_id, deleted_at, sort_order),
                  CONSTRAINT fk_rule_categories_user FOREIGN KEY (user_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $exists = $pdo->query("SHOW TABLES LIKE 'own_rules'")->fetch();
        if (!$exists) {
            $pdo->exec(
                "CREATE TABLE own_rules (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  user_id BIGINT UNSIGNED NOT NULL,
                  category_id BIGINT UNSIGNED NOT NULL,
                  title VARCHAR(255) NOT NULL,
                  body TEXT NULL,
                  sort_order INT NOT NULL DEFAULT 0,
                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  deleted_at DATETIME NULL,
                  KEY idx_own_rules_user_cat (user_id, category_id, deleted_at, sort_order),
                  CONSTRAINT fk_own_rules_user FOREIGN KEY (user_id) REFERENCES users(id),
                  CONSTRAINT fk_own_rules_category FOREIGN KEY (category_id) REFERENCES rule_categories(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $done = true;
    }

    /** @return list<array{category:array,rules:list<array>}> */
    public function listGrouped(int $userId): array
    {
        $this->ensureTables();
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            'SELECT id, name, sort_order
             FROM rule_categories
             WHERE user_id = :uid AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId]);
        $categories = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT id, category_id, title, body, sort_order
             FROM own_rules
             WHERE user_id = :uid AND deleted_at IS NULL
             ORDER BY category_id ASC, sort_order ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId]);
        $byCategory = [];
        foreach ($stmt->fetchAll() as $rule) {
            $byCategory[(int) $rule['category_id']][] = $rule;
        }

        $grouped = [];
        foreach ($categories as $category) {
            $grouped[] = [
                'category' => $category,
                'rules' => $byCategory[(int) $category['id']] ?? [],
            ];
        }
        return $grouped;
    }

    public function createCategory(int $userId, string $name): int
    {
        $this->ensureTables();
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre es obligatorio');
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM rule_categories WHERE user_id = ? AND deleted_at IS NULL');
        $stmt->execute([$userId]);
        $next = (int) $stmt->fetchColumn();

        $ins = $pdo->prepare(
            'INSERT INTO rule_categories (user_id, name, sort_order) VALUES (:uid, :name, :sort)'
        );
        $ins->execute(['uid' => $userId, 'name' => mb_substr($name, 0, 160), 'sort' => $next]);
        return (int) $pdo->lastInsertId();
    }

    public function updateCategory(int $userId, int $categoryId, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre es obligatorio');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE rule_categories SET name = :name
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['name' => mb_substr($name, 0, 160), 'id' => $categoryId, 'uid' => $userId]);
        if ($stmt->rowCount() === 0 && !$this->categoryExists($userId, $categoryId)) {
            throw new RuntimeException('Categoría no encontrada');
        }
    }

    public function deleteCategory(int $userId, int $categoryId): void
    {
        if (!$this->categoryExists($userId, $categoryId)) {
            throw new RuntimeException('Categoría no encontrada');
        }
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE own_rules SET deleted_at = CURRENT_TIMESTAMP
             WHERE category_id = :cid AND user_id = :uid AND deleted_at IS NULL'
        )->execute(['cid' => $categoryId, 'uid' => $userId]);
        $pdo->prepare(
            'UPDATE rule_categories SET deleted_at = CURRENT_TIMESTAMP
             WHERE id = :cid AND user_id = :uid AND deleted_at IS NULL'
        )->execute(['cid' => $categoryId, 'uid' => $userId]);
    }

    public function createRule(int $userId, int $categoryId, string $title, ?string $body = null): int
    {
        $this->ensureTables();
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('El texto de la regla es obligatorio');
        }
        if (!$this->categoryExists($userId, $categoryId)) {
            throw new RuntimeException('Categoría no encontrada');
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM own_rules
             WHERE user_id = ? AND category_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$userId, $categoryId]);
        $next = (int) $stmt->fetchColumn();

        $ins = $pdo->prepare(
            'INSERT INTO own_rules (user_id, category_id, title, body, sort_order)
             VALUES (:uid, :cid, :title, :body, :sort)'
        );
        $ins->execute([
            'uid' => $userId,
            'cid' => $categoryId,
            'title' => mb_substr($title, 0, 255),
            'body' => $body !== null && trim($body) !== '' ? trim($body) : null,
            'sort' => $next,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function updateRule(int $userId, int $ruleId, string $title, ?string $body = null): void
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('El texto de la regla es obligatorio');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE own_rules SET title = :title, body = :body
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute([
            'title' => mb_substr($title, 0, 255),
            'body' => $body !== null && trim($body) !== '' ? trim($body) : null,
            'id' => $ruleId,
            'uid' => $userId,
        ]);
    }

    public function deleteRule(int $userId, int $ruleId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT category_id FROM own_rules
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $ruleId, 'uid' => $userId]);
        $categoryId = $stmt->fetchColumn();
        if ($categoryId === false) {
            throw new RuntimeException('Regla no encontrada');
        }

        $pdo->prepare(
            'UPDATE own_rules SET deleted_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :uid'
        )->execute(['id' => $ruleId, 'uid' => $userId]);

        $this->renumberRules($userId, (int) $categoryId);
    }

    private function renumberRules(int $userId, int $categoryId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM own_rules
             WHERE user_id = :uid AND category_id = :cid AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId, 'cid' => $categoryId]);
        $upd = $pdo->prepare('UPDATE own_rules SET sort_order = :n WHERE id = :id');
        $n = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $n++;
            $upd->execute(['n' => $n, 'id' => (int) $id]);
        }
    }

    private function categoryExists(int $userId, int $categoryId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM rule_categories
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $categoryId, 'uid' => $userId]);
        return (bool) $stmt->fetchColumn();
    }
}
