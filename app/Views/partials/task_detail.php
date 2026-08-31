<?php
/**
 * Detalle expandible de una tarea del plan semanal: imagen adjunta + checklist de pasos.
 *
 * @var array<string,mixed> $task
 * @var string $returnPath adónde volver tras un POST (conserva semana/día o /today)
 */
$taskId = (int) $task['id'];
$steps = $task['steps'] ?? [];
$img = (string) ($task['image_path'] ?? '');
?>
<div class="task-detail" data-task-detail hidden>
    <?php if ($img !== ''): ?>
        <figure class="task-image">
            <a href="<?= e(url('/' . $img)) ?>" target="_blank" rel="noopener" title="Ver imagen completa">
                <img src="<?= e(url('/' . $img)) ?>" alt="Imagen de la tarea" loading="lazy">
            </a>
            <form method="post" action="<?= e(form_action()) ?>" data-lq-save onsubmit="return confirm('¿Quitar la imagen de esta tarea?');">
                <?= csrf_field() ?>
                <?= route_field('/weekly/' . $taskId . '/image') ?>
                <input type="hidden" name="remove" value="1">
                <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
                <button type="submit" class="btn btn-ghost btn-sm">Quitar imagen</button>
            </form>
        </figure>
    <?php else: ?>
        <form method="post" action="<?= e(form_action()) ?>" enctype="multipart/form-data" class="task-image-form" data-no-lq-save>
            <?= csrf_field() ?>
            <?= route_field('/weekly/' . $taskId . '/image') ?>
            <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
            <label class="btn btn-ghost btn-sm task-upload-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m5 19 5-6 3 3.5L16 12l3 7"/></svg>
                Agregar imagen
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" hidden onchange="this.form.submit()">
            </label>
            <span class="muted tiny">JPG, PNG, WEBP o GIF · hasta 5 MB</span>
        </form>
    <?php endif; ?>

    <?php if ($steps !== []): ?>
        <ul class="task-steps">
            <?php foreach ($steps as $stepIndex => $step):
                $sdone = (int) $step['is_done'] === 1;
                ?>
                <li class="task-step<?= $sdone ? ' is-done' : '' ?>">
                    <span class="habit-num task-num rule-num"><?= (int) $stepIndex + 1 ?></span>
                    <form method="post" action="<?= e(form_action()) ?>" class="habit-toggle-form" data-step-toggle>
                        <?= csrf_field() ?>
                        <?= route_field('/weekly/steps/' . (int) $step['id'] . '/toggle') ?>
                        <label class="check-toggle">
                            <input type="checkbox" <?= $sdone ? 'checked' : '' ?> aria-label="Paso: <?= e((string) $step['title']) ?>">
                            <span></span>
                        </label>
                    </form>
                    <span class="task-step-title"><?= e((string) $step['title']) ?></span>
                    <form method="post" action="<?= e(form_action()) ?>" class="task-step-delete" data-lq-save onsubmit="return confirm('¿Eliminar este paso?');">
                        <?= csrf_field() ?>
                        <?= route_field('/weekly/steps/' . (int) $step['id'] . '/delete') ?>
                        <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" title="Eliminar paso">×</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= e(form_action()) ?>" class="weekly-add-form task-step-add" data-lq-save>
        <?= csrf_field() ?>
        <?= route_field('/weekly/' . $taskId . '/steps') ?>
        <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
        <div class="weekly-add-main">
            <input type="text" name="title" required maxlength="255" placeholder="Nuevo paso…" aria-label="Nuevo paso para <?= e((string) $task['title']) ?>">
            <button type="submit" class="btn btn-ghost btn-sm">+</button>
        </div>
    </form>
</div>
