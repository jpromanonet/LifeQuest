<?php
/** @var list<array> $habits */
/** @var array|null $selected */
/** @var list<array> $areas */
/** @var string $todayDate */
/** @var array<int,string> $monthLabels */
/** @var int $habitYear */

$monthLabels = $monthLabels ?? MonthProgress::MONTH_LABELS;
$habitYear = $habitYear ?? (int) date('Y');
?>
<section class="page-header with-actions">
    <div>
        <h1>Hábitos</h1>
        <p class="muted">Seguimiento por meses o por unidades (libros, km, etc.)</p>
    </div>
    <button type="button" class="btn btn-primary" data-open-modal="habitModal">+ Nuevo hábito</button>
</section>

<div class="card">
    <ul class="habit-list habit-list-full">
        <?php if ($habits === []): ?>
            <li class="empty-state">Todavía no tenés hábitos activos.</li>
        <?php endif; ?>
        <?php foreach ($habits as $habit):
            $num = (int) ($habit['display_number'] ?? 0);
            $group = (int) ($habit['color_group'] ?? habit_color_group($num));
            $mode = (string) ($habit['tracking_mode'] ?? 'months');
            $done = ($habit['log_status'] ?? null) === 'completed' || ($habit['log_status'] ?? null) === 'partial';
            $monthChecks = $habit['month_checks'] ?? MonthProgress::emptyMap();
            $monthsCsv = [];
            for ($m = 1; $m <= 12; $m++) {
                if (!empty($monthChecks[$m])) {
                    $monthsCsv[] = $m;
                }
            }
            ?>
            <li
                class="habit-row<?= $done ? ' is-done' : '' ?>"
                data-color-group="<?= $group ?>"
                data-habit-id="<?= (int) $habit['id'] ?>"
                data-edit-habit
                data-id="<?= (int) $habit['id'] ?>"
                data-name="<?= e((string) $habit['name']) ?>"
                data-description="<?= e((string) ($habit['description'] ?? '')) ?>"
                data-area-id="<?= e((string) ($habit['area_id'] ?? '')) ?>"
                data-tracking-mode="<?= e($mode) ?>"
                data-target="<?= e((string) (float) ($habit['target_per_period'] ?? 12)) ?>"
                data-current="<?= e((string) (float) ($habit['current_value'] ?? 0)) ?>"
                data-unit="<?= e((string) ($habit['unit'] ?? 'unidades')) ?>"
                data-progress="<?= e((string) (int) ($habit['progress_percent'] ?? 0)) ?>"
                data-months="<?= e(implode(',', $monthsCsv)) ?>"
                data-frequency="<?= e((string) ($habit['frequency_type'] ?? 'daily')) ?>"
                role="button"
                tabindex="0"
            >
                <span class="habit-num"><?= $num ?></span>
                <span class="habit-meta">
                    <span class="habit-name"><?= e((string) $habit['name']) ?></span>
                    <span class="muted small">
                        <?php if ($mode === 'units'): ?>
                            <?= e(number_format((float) ($habit['current_value'] ?? 0), 0)) ?> /
                            <?= e(number_format((float) ($habit['target_per_period'] ?? 0), 0)) ?>
                            <?= e((string) ($habit['unit'] ?? 'unidades')) ?>
                            · <?= e(number_format((float) ($habit['progress_percent'] ?? 0), 0)) ?>%
                        <?php elseif ($mode === 'months'): ?>
                            Meses · <?= e(number_format((float) ($habit['progress_percent'] ?? 0), 0)) ?>%
                        <?php else: ?>
                            <?= e((string) ($habit['area_name'] ?? $habit['frequency_type'] ?? '')) ?>
                        <?php endif; ?>
                    </span>
                </span>
                <?php if ($mode === 'daily'): ?>
                    <form method="post" action="<?= e(form_action()) ?>" class="habit-toggle-form" data-habit-toggle onclick="event.stopPropagation()">
                        <?= csrf_field() ?>
                        <?= route_field('/habits/log') ?>
                        <input type="hidden" name="habit_id" value="<?= (int) $habit['id'] ?>">
                        <input type="hidden" name="date" value="<?= e($todayDate) ?>">
                        <input type="hidden" name="status" value="<?= $done ? 'missed' : 'completed' ?>">
                        <input type="hidden" name="redirect" value="/habits">
                        <label class="check-toggle">
                            <input type="checkbox" <?= $done ? 'checked' : '' ?> aria-label="Marcar <?= e((string) $habit['name']) ?>">
                            <span></span>
                        </label>
                    </form>
                <?php elseif ($mode === 'units'): ?>
                    <form method="post" action="<?= e(form_action()) ?>" class="habit-toggle-form" data-lq-save onclick="event.stopPropagation()">
                        <?= csrf_field() ?>
                        <?= route_field('/habits/' . (int) $habit['id'] . '/units') ?>
                        <input type="hidden" name="delta" value="1">
                        <button type="submit" class="btn btn-ghost btn-sm" title="Sumar 1">+1</button>
                    </form>
                <?php else: ?>
                    <span class="muted small"><?= e(number_format((float) ($habit['progress_percent'] ?? 0), 0)) ?>%</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<dialog
    class="modal"
    id="habitEditModal"
    <?php if ($selected):
        $selMode = (string) ($selected['tracking_mode'] ?? 'months');
        $selMonths = $selected['month_checks'] ?? ($selectedMonths ?? MonthProgress::emptyMap());
        $selCsv = [];
        for ($m = 1; $m <= 12; $m++) {
            if (!empty($selMonths[$m])) {
                $selCsv[] = $m;
            }
        }
        ?>
        data-auto-open="1"
        data-id="<?= (int) $selected['id'] ?>"
        data-name="<?= e((string) $selected['name']) ?>"
        data-description="<?= e((string) ($selected['description'] ?? '')) ?>"
        data-area-id="<?= e((string) ($selected['area_id'] ?? '')) ?>"
        data-tracking-mode="<?= e($selMode) ?>"
        data-target="<?= e((string) (float) ($selected['target_per_period'] ?? 12)) ?>"
        data-current="<?= e((string) (float) ($selected['current_value'] ?? 0)) ?>"
        data-unit="<?= e((string) ($selected['unit'] ?? 'unidades')) ?>"
        data-progress="<?= e((string) (int) ($selected['progress_percent'] ?? 0)) ?>"
        data-months="<?= e(implode(',', $selCsv)) ?>"
        data-frequency="<?= e((string) ($selected['frequency_type'] ?? 'daily')) ?>"
    <?php endif; ?>
>
    <form method="post" id="habitEditForm" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="habitEditRoute" value="/habits/0">
        <header class="modal-head">
            <h2>Editar hábito</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field"><span>Nombre</span><input type="text" name="name" id="habitEditName" required maxlength="160"></label>
        <label class="field"><span>Descripción</span><textarea name="description" id="habitEditDescription" rows="2"></textarea></label>
        <label class="field">
            <span>Área</span>
            <select name="area_id" id="habitEditAreaId">
                <option value="">Sin área</option>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= (int) $area['id'] ?>"><?= e((string) $area['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Medición</span>
            <select name="tracking_mode" id="habitEditTrackingMode">
                <option value="months">Por meses (12)</option>
                <option value="units">Por unidades</option>
                <option value="daily">Diario</option>
            </select>
        </label>
        <div class="form-grid-2" id="habitEditUnitsFields" hidden>
            <label class="field"><span>Meta</span><input type="number" name="target_per_period" id="habitEditTarget" min="1" value="12"></label>
            <label class="field"><span>Unidad</span><input type="text" name="unit" id="habitEditUnit" value="unidades"></label>
        </div>
        <label class="field" id="habitEditFrequencyField" hidden>
            <span>Frecuencia</span>
            <select name="frequency_type" id="habitEditFrequency">
                <option value="daily">Diario</option>
                <option value="weekdays">Días hábiles</option>
                <option value="custom_days">Días personalizados</option>
                <option value="weekly">Semanal</option>
                <option value="monthly">Mensual</option>
            </select>
        </label>

        <div class="field" id="habitEditMonthsBlock" hidden>
            <div class="month-progress-head">
                <span>Meses <?= (int) $habitYear ?></span>
                <strong id="habitEditProgressLabel">0%</strong>
            </div>
            <p class="muted small">Tildá los meses en los que cumpliste este hábito.</p>
            <div class="month-grid" id="habitEditMonthGrid" data-habit-id="">
                <?php foreach ($monthLabels as $num => $label): ?>
                    <label class="month-chip">
                        <input type="checkbox" data-month="<?= (int) $num ?>" value="<?= (int) $num ?>">
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field" id="habitEditUnitsBlock" hidden>
            <div class="month-progress-head">
                <span>Progreso</span>
                <strong id="habitEditUnitsLabel">0%</strong>
            </div>
            <div class="units-grid units-grid--2">
                <label class="field">
                    <span>Hechas</span>
                    <input type="number" id="habitEditCurrent" min="0" step="1" value="0">
                </label>
                <label class="field">
                    <span>Meta</span>
                    <input type="number" id="habitEditTargetLive" min="1" step="1" value="12">
                </label>
            </div>
            <div class="btn-row" style="margin-top:10px">
                <button type="button" class="btn btn-ghost btn-sm" id="habitEditUnitsPlus">+1</button>
                <button type="button" class="btn btn-primary btn-sm" id="habitEditUnitsSave">Guardar unidades</button>
            </div>
        </div>

        <footer class="modal-foot modal-foot-split">
            <div class="btn-row">
                <button type="submit" form="habitArchiveForm" class="btn btn-ghost">Archivar</button>
                <button type="submit" form="habitDeleteForm" class="btn btn-danger" onclick="return confirm('¿Eliminar este hábito?');">Eliminar</button>
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </footer>
    </form>
    <form method="post" id="habitArchiveForm" action="<?= e(form_action()) ?>" hidden data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="habitArchiveRoute" value="/habits/0/archive">
    </form>
    <form method="post" id="habitDeleteForm" action="<?= e(form_action()) ?>" hidden data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="habitDeleteRoute" value="/habits/0/delete">
    </form>
    <form method="post" id="habitMonthToggleForm" action="<?= e(form_action()) ?>" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="habitMonthRoute" value="/habits/0/months">
        <input type="hidden" name="year" value="<?= (int) $habitYear ?>">
        <input type="hidden" name="month" id="habitMonthValue" value="">
    </form>
    <form method="post" id="habitUnitsForm" action="<?= e(form_action()) ?>" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="habitUnitsRoute" value="/habits/0/units">
    </form>
</dialog>

<dialog class="modal" id="habitModal">
    <form method="post" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save>
        <?= csrf_field() ?>
        <?= route_field('/habits') ?>
        <header class="modal-head">
            <h2>Nuevo hábito</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field"><span>Nombre</span><input type="text" name="name" required maxlength="160" placeholder="Ej. Leer libros"></label>
        <label class="field"><span>Descripción</span><textarea name="description" rows="2"></textarea></label>
        <label class="field">
            <span>¿Cómo lo medís?</span>
            <select name="tracking_mode" id="habitTrackingMode">
                <option value="months">Por meses (tildar los 12 del año)</option>
                <option value="units">Por unidades (libros, km, sesiones…)</option>
                <option value="daily">Diario (check del día)</option>
            </select>
        </label>
        <div class="form-grid-2" id="habitUnitsFields" hidden>
            <label class="field">
                <span>Meta (cantidad)</span>
                <input type="number" name="target_per_period" min="1" value="12" placeholder="Ej. 12">
            </label>
            <label class="field">
                <span>Unidad</span>
                <input type="text" name="unit" value="libros" placeholder="libros, km…">
            </label>
        </div>
        <div class="form-grid-2">
            <label class="field">
                <span>Área</span>
                <select name="area_id">
                    <option value="">Sin área</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?= (int) $area['id'] ?>"><?= e((string) $area['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Frecuencia (si es diario)</span>
                <select name="frequency_type">
                    <option value="daily">Diario</option>
                    <option value="weekdays">Días hábiles</option>
                    <option value="custom_days">Días personalizados</option>
                    <option value="weekly">Semanal</option>
                    <option value="monthly">Mensual</option>
                </select>
            </label>
        </div>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear</button>
        </footer>
    </form>
</dialog>
