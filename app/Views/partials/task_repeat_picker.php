<?php
/**
 * Días de la semana en la que se está planificando (no es una serie eterna).
 * Sin marcar: la tarea queda solo en el día del formulario.
 * El día de origen y los días donde ya existe el mismo título no se pueden elegir.
 *
 * @var int|null $currentDow día ISO (1=lun…7=dom) del formulario, para resaltar
 */
$currentDow = isset($currentDow) ? (int) $currentDow : 0;
?>
<fieldset class="task-repeat-picker">
    <legend class="sr-only">También estos días de esta semana</legend>
    <span class="task-repeat-label" aria-hidden="true">También en</span>
    <?php foreach (WeeklyPlanService::DAY_LABELS as $dow => $label):
        $isOrigin = $currentDow === $dow;
        ?>
        <label class="task-repeat-chip<?= $isOrigin ? ' is-today-day is-disabled' : '' ?>"<?= $isOrigin ? ' title="Ya está en este día"' : '' ?>>
            <input type="checkbox" name="repeat_days[]" value="<?= (int) $dow ?>"<?= $isOrigin ? ' disabled' : '' ?>>
            <span><?= e($label) ?></span>
        </label>
    <?php endforeach; ?>
</fieldset>
