<?php
/**
 * Iconos de la tarea: imagen adjunta y/o checklist de pasos.
 *
 * @var array<string,mixed> $task
 */
$hasImage = !empty($task['image_path']);
$stepsTotal = (int) ($task['steps_total'] ?? 0);
$stepsDone = (int) ($task['steps_done'] ?? 0);
?>
<?php if ($hasImage || $stepsTotal > 0): ?>
    <span class="task-flags" data-task-expand role="button" title="Ver imagen y pasos">
        <?php if ($hasImage): ?>
            <span class="task-flag" aria-label="Tiene imagen">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m5 19 5-6 3 3.5L16 12l3 7"/></svg>
            </span>
        <?php endif; ?>
        <?php if ($stepsTotal > 0): ?>
            <span class="task-flag" aria-label="Tiene pasos">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 6 1.5 1.5L7 5"/><path d="m3 12 1.5 1.5L7 11"/><path d="m3 18 1.5 1.5L7 17"/><path d="M10 6h11M10 12h11M10 18h11"/></svg>
                <span class="task-flag-count" data-steps-count><?= $stepsDone ?>/<?= $stepsTotal ?></span>
            </span>
        <?php endif; ?>
    </span>
<?php endif; ?>
