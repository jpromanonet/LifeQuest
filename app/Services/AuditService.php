<?php

declare(strict_types=1);

final class AuditService
{
    /** @param array<string, mixed>|null $meta */
    public function log(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $meta = null
    ): void {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta_json)
             VALUES (:user_id, :action, :entity_type, :entity_id, :meta_json)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'meta_json' => $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
