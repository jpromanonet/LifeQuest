<?php
/**
 * Lista de tareas del día, partida en Laborales / Personales.
 *
 * @var list<array<string,mixed>> $tasks
 * @var string $returnPath
 * @var bool $showDelete
 * @var string $dayFilter
 */
$tasks = $tasks ?? [];
$returnPath = $returnPath ?? '/weekly';
$showDelete = !empty($showDelete);
$dayFilter = $dayFilter ?? '';
$groups = [
    'work' => ['label' => 'Laborales', 'items' => []],
    'personal' => ['label' => 'Personales', 'items' => []],
];
foreach ($tasks as $task) {
    $kind = (($task['task_kind'] ?? '') === 'work') ? 'work' : 'personal';
    $groups[$kind]['items'][] = $task;
}
?>
<ul class="habit-list weekly-task-list">
    <?php foreach ($groups as $kind => $group): ?>
        <li class="task-kind-divider" data-task-kind="<?= e($kind) ?>">
            <span><?= e((string) $group['label']) ?></span>
        </li>
        <li class="empty-state muted small task-kind-empty" data-empty-kind="<?= e($kind) ?>" <?= $group['items'] === [] ? '' : 'hidden' ?>>Sin tareas</li>
        <?php foreach ($group['items'] as $taskIndex => $task):
            $done = (int) ($task['is_done'] ?? 0) === 1;
            ?>
            <li class="habit-row weekly-task-row<?= $done ? ' is-done' : '' ?>" data-task-id="<?= (int) $task['id'] ?>" data-task-kind="<?= e($kind) ?>">
                <span class="habit-drag" data-drag-handle title="Arrastrar para cambiar de tipo u orden" aria-label="Arrastrar tarea">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
                </span>
                <span class="habit-num task-num"><?= (int) $taskIndex + 1 ?></span>
                <form method="post" action="<?= e(form_action()) ?>" class="habit-toggle-form" data-task-toggle>
                    <?= csrf_field() ?>
                    <?= route_field('/weekly/' . (int) $task['id'] . '/toggle') ?>
                    <input type="hidden" name="status" value="<?= $done ? 'pending' : 'completed' ?>">
                    <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
                    <label class="check-toggle">
                        <input type="checkbox" <?= $done ? 'checked' : '' ?> aria-label="Marcar <?= e((string) $task['title']) ?>">
                        <span></span>
                    </label>
                </form>
                <span class="habit-name">
                    <?= e((string) $task['title']) ?>
                    <?php include __DIR__ . '/task_when.php'; ?>
                    <?php include __DIR__ . '/task_flags.php'; ?>
                </span>
                <div class="weekly-task-actions">
                    <button type="button" class="btn btn-ghost btn-sm task-expand-btn" data-task-expand aria-expanded="false" title="Imagen y pasos">▾</button>
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm"
                        data-edit-task
                        data-id="<?= (int) $task['id'] ?>"
                        data-title="<?= e((string) $task['title']) ?>"
                        data-date="<?= e((string) $task['task_date']) ?>"
                        data-kind="<?= e($kind) ?>"
                        data-notes="<?= e((string) ($task['notes'] ?? '')) ?>"
                        data-start="<?= e(format_task_time($task['start_time'] ?? null)) ?>"
                        data-minutes="<?= e((string) ((int) ($task['estimated_minutes'] ?? 0) > 0 ? (int) $task['estimated_minutes'] : '')) ?>"
                    >Editar</button>
                    <?php if ($showDelete): ?>
                        <form method="post" action="<?= e(form_action()) ?>" data-lq-save onsubmit="return confirm('¿Eliminar esta tarea?');">
                            <?= csrf_field() ?>
                            <?= route_field('/weekly/' . (int) $task['id'] . '/delete') ?>
                            <input type="hidden" name="filter_day" value="<?= e($dayFilter) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">×</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php include __DIR__ . '/task_detail.php'; ?>
            </li>
        <?php endforeach; ?>
    <?php endforeach; ?>
</ul>
