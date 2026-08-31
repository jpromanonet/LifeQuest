<?php
/**
 * Chip de horario de inicio y tiempo estimado de una tarea.
 *
 * @var array<string,mixed> $task
 */
$startLabel = format_task_time($task['start_time'] ?? null);
$durationLabel = format_task_duration($task['estimated_minutes'] ?? null);
if ($startLabel === '' && $durationLabel === '') {
    return;
}
$bits = array_values(array_filter([$startLabel, $durationLabel], static fn (string $s): bool => $s !== ''));
?>
<span class="task-when" title="Horario previsto"><?= e(implode(' · ', $bits)) ?></span>
