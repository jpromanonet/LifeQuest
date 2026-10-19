<?php
/** @var array<int, list<array<string, mixed>>> $board */
/** @var array<string, string> $colors */
/** @var array<int, string> $dayLabels */
/** @var array<int, string> $dayShort */
/** @var list<array{minute:int,label:string}> $startSlots */
/** @var list<array{minute:int,label:string}> $endSlots */
/** @var int $dayStart */
/** @var int $dayEnd */
/** @var int $snap */
/** @var int $todayWeekday */

$span = max(1, $dayEnd - $dayStart);
$hourCount = (int) ($span / 60);
?>
<section class="page-header with-actions">
    <div class="horario-heading">
        <h1>Horario</h1>
        <p class="muted">Tu semana fija, como cuando ibas al colegio. Tocá un hueco para sumar un bloque.</p>
    </div>
    <div class="btn-row horario-actions">
        <button type="button" class="btn btn-ghost" id="horarioExport">
            <svg class="horario-btn-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v10"/><path d="m8 10 4 4 4-4"/><path d="M5 19h14"/></svg>
            <span class="horario-btn-long">Exportar imagen</span>
            <span class="horario-btn-short">Exportar</span>
        </button>
        <button type="button" class="btn btn-primary" id="horarioAdd">
            <svg class="horario-btn-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            <span class="horario-btn-long">+ Bloque</span>
            <span class="horario-btn-short">Bloque</span>
        </button>
    </div>
</section>

<section
    class="horario-board card"
    id="horarioBoard"
    data-day-start="<?= (int) $dayStart ?>"
    data-day-end="<?= (int) $dayEnd ?>"
    data-snap="<?= (int) $snap ?>"
    style="--horario-hours: <?= (int) $hourCount ?>"
>
    <nav class="horario-day-switch" id="horarioDaySwitch" aria-label="Elegir día">
        <?php for ($day = 1; $day <= 7; $day++): ?>
            <button
                type="button"
                class="horario-day-btn<?= $day === $todayWeekday ? ' is-today is-active' : '' ?>"
                data-weekday="<?= $day ?>"
                aria-label="<?= e($dayLabels[$day]) ?>"
                aria-pressed="<?= $day === $todayWeekday ? 'true' : 'false' ?>"
            >
                <span><?= e($dayShort[$day]) ?></span>
                <?php if ($day === $todayWeekday): ?><i aria-hidden="true"></i><?php endif; ?>
            </button>
        <?php endfor; ?>
    </nav>
    <div class="horario-scroll">
        <div class="horario-head">
            <div class="horario-corner"></div>
            <?php for ($day = 1; $day <= 7; $day++): ?>
                <div class="horario-day-name<?= $day === $todayWeekday ? ' is-today' : '' ?>">
                    <span><?= e($dayShort[$day]) ?></span>
                    <?php if ($day === $todayWeekday): ?><em>hoy</em><?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
        <div class="horario-body">
            <div class="horario-gutter" aria-hidden="true">
                <?php for ($minute = $dayStart; $minute <= $dayEnd; $minute += 60):
                    $top = round((($minute - $dayStart) / $span) * 100, 4);
                    $isStart = $minute === $dayStart;
                    $isEnd = $minute === $dayEnd;
                    $tickClass = $isStart ? ' is-start' : ($isEnd ? ' is-end' : '');
                    ?>
                    <span class="horario-tick<?= $tickClass ?>" style="top: <?= e((string) $top) ?>%"><?= e(ScheduleService::formatMinute($minute)) ?></span>
                <?php endfor; ?>
            </div>
            <?php for ($day = 1; $day <= 7; $day++): ?>
                <div
                    class="horario-col<?= $day === $todayWeekday ? ' is-today is-selected' : '' ?>"
                    data-weekday="<?= $day ?>"
                    data-day-label="<?= e($dayLabels[$day]) ?>"
                >
                    <?php foreach ($board[$day] as $block):
                        $start = (int) $block['start_minute'];
                        $end = (int) $block['end_minute'];
                        $top = round((($start - $dayStart) / $span) * 100, 4);
                        $height = round((($end - $start) / $span) * 100, 4);
                        $lane = (int) $block['lane'];
                        $lanes = max(1, (int) $block['lanes']);
                        ?>
                        <article
                            class="horario-block"
                            data-color="<?= e((string) $block['color']) ?>"
                            data-id="<?= (int) $block['id'] ?>"
                            data-title="<?= e((string) $block['title']) ?>"
                            data-notes="<?= e((string) $block['notes']) ?>"
                            data-weekday="<?= $day ?>"
                            data-series="<?= (int) ($block['series_id'] ?? $block['id']) ?>"
                            data-start="<?= $start ?>"
                            data-end="<?= $end ?>"
                            data-start-label="<?= e((string) $block['start_label']) ?>"
                            data-end-label="<?= e((string) $block['end_label']) ?>"
                            style="top: <?= e((string) $top) ?>%; height: calc(<?= e((string) $height) ?>% - 3px); left: calc(<?= $lane ?> * (100% / <?= $lanes ?>) + 4px); width: calc(100% / <?= $lanes ?> - 8px);"
                        >
                            <strong><?= e((string) $block['title']) ?></strong>
                            <span class="horario-time"><?= e((string) $block['start_label']) ?> – <?= e((string) $block['end_label']) ?></span>
                            <em class="horario-note"><?= e((string) $block['notes']) ?></em>
                            <i class="horario-resize" data-resize aria-hidden="true"></i>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<dialog class="modal" id="horarioModal" aria-labelledby="horarioModalTitle">
    <form method="post" action="<?= e(form_action()) ?>" class="stack-form" id="horarioForm" data-no-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="horarioRoute" value="/horario">
        <input type="hidden" name="id" id="horarioId" value="">
        <div class="modal-head">
            <h2 id="horarioModalTitle">Nuevo bloque</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </div>
        <p class="horario-form-error" id="horarioError" hidden></p>
        <label class="field">
            <span>Nombre</span>
            <input type="text" name="title" id="horarioTitle" required maxlength="120" placeholder="Ej. Trabajo, yoga, almuerzo">
        </label>
        <div class="horario-form-grid">
            <label class="field">
                <span>Día</span>
                <select name="weekday" id="horarioWeekday">
                    <?php foreach ($dayLabels as $num => $label): ?>
                        <option value="<?= (int) $num ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Desde</span>
                <select name="start_minute" id="horarioStart">
                    <?php foreach ($startSlots as $slot): ?>
                        <option value="<?= (int) $slot['minute'] ?>"><?= e($slot['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Hasta</span>
                <select name="end_minute" id="horarioEnd">
                    <?php foreach ($endSlots as $slot): ?>
                        <option value="<?= (int) $slot['minute'] ?>"><?= e($slot['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <fieldset class="task-repeat-picker" id="horarioRepeat">
            <legend class="sr-only">También estos días</legend>
            <span class="task-repeat-label" aria-hidden="true">También en</span>
            <?php foreach (ScheduleService::DAY_SHORT as $dow => $label): ?>
                <label class="task-repeat-chip">
                    <input type="checkbox" name="repeat_days[]" value="<?= (int) $dow ?>">
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <fieldset class="horario-colors">
            <legend>Color</legend>
            <?php $first = true; foreach ($colors as $key => $label): ?>
                <label class="horario-color" title="<?= e($label) ?>">
                    <input type="radio" name="color" value="<?= e($key) ?>" <?= $first ? 'checked' : '' ?>>
                    <span class="horario-swatch" data-color="<?= e($key) ?>"></span>
                    <span class="sr-only"><?= e($label) ?></span>
                </label>
            <?php $first = false; endforeach; ?>
        </fieldset>
        <label class="field">
            <span>Nota <span class="muted">(opcional)</span></span>
            <textarea name="notes" id="horarioNotes" rows="2" maxlength="500" placeholder="Algo corto para recordar"></textarea>
        </label>
        <div class="modal-foot modal-foot-split">
            <button type="button" class="btn btn-danger" id="horarioDelete" hidden>Eliminar</button>
            <span class="horario-foot-actions">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </span>
        </div>
    </form>
</dialog>

<dialog class="modal horario-scope" id="horarioScope" aria-labelledby="horarioScopeTitle">
    <div class="stack-form">
        <div class="modal-head">
            <h2 id="horarioScopeTitle">Este bloque se repite</h2>
            <button type="button" class="icon-btn" id="horarioScopeClose" aria-label="Cerrar">×</button>
        </div>
        <p id="horarioScopeText">¿Aplicar el cambio solo a este día o a todos?</p>
        <div class="horario-scope-actions">
            <button type="button" class="btn btn-primary" id="horarioScopeThis">Solo este</button>
            <button type="button" class="btn btn-ghost" id="horarioScopeAll">Todos los días</button>
            <button type="button" class="btn btn-ghost" id="horarioScopeCancel">Cancelar</button>
        </div>
    </div>
</dialog>
