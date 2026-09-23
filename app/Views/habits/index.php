<?php
/** @var list<array> $habits */
/** @var array|null $selected */
/** @var string $todayDate */
/** @var string $habitDate */
/** @var bool $isHabitToday */
/** @var list<array{date:string,label:string,is_today:bool}> $habitWeekDays */
/** @var array<int,string> $monthLabels */
/** @var int $habitYear */

$monthLabels = $monthLabels ?? MonthProgress::MONTH_LABELS;
$habitYear = $habitYear ?? (int) date('Y');
$habitDate = $habitDate ?? $todayDate;
$isHabitToday = $isHabitToday ?? true;
$habitWeekDays = $habitWeekDays ?? [];
$habitPrevWeek = $habitPrevWeek ?? $habitDate;
$habitNextWeek = $habitNextWeek ?? $habitDate;
$canGoNextHabitWeek = $canGoNextHabitWeek ?? false;
$isCurrentHabitWeek = $isCurrentHabitWeek ?? true;
$habitReturnPath = $habitReturnPath ?? '/habits';
$realTodayDate = $todayDate;

$habitDayUrl = static function (string $date) use ($realTodayDate): string {
    if ($date === '' || $date === $realTodayDate) {
        return url('/habits');
    }
    return url('/habits?' . http_build_query(['date' => $date]));
};
?>
<section class="page-header with-actions">
    <div>
        <h1>Hábitos</h1>
        <p class="muted">Seguimiento por meses o por unidades (libros, km, etc.)</p>
    </div>
    <button type="button" class="btn btn-primary" data-open-modal="habitModal">+ Nuevo hábito</button>
</section>

<div class="card">
    <div class="habit-day-bar">
        <label class="habit-day-select-wrap" for="habitDaySelect">
            <span class="muted small">Día</span>
            <select id="habitDaySelect" class="habit-day-select" aria-label="Elegir día de la semana">
                <?php foreach ($habitWeekDays as $day): ?>
                    <option
                        value="<?= e($habitDayUrl((string) $day['date'])) ?>"
                        <?= ((string) $day['date'] === $habitDate) ? 'selected' : '' ?>
                    >
                        <?= e((string) $day['label']) ?><?= !empty($day['is_today']) ? ' · hoy' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="btn-row habit-week-nav">
            <a class="btn btn-ghost btn-sm" href="<?= e($habitDayUrl($habitPrevWeek)) ?>" title="Semana anterior">←</a>
            <?php if (!$isCurrentHabitWeek || !$isHabitToday): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/habits')) ?>">Hoy</a>
            <?php endif; ?>
            <?php if ($canGoNextHabitWeek): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e($habitDayUrl($habitNextWeek)) ?>" title="Semana siguiente">→</a>
            <?php endif; ?>
        </div>
    </div>
    <ul class="habit-list habit-list-full">
        <?php if ($habits === []): ?>
            <li class="empty-state">Todavía no tenés hábitos activos.</li>
        <?php endif; ?>
        <?php foreach ($habits as $habit):
            $num = (int) ($habit['display_number'] ?? 0);
            $group = (int) ($habit['color_group'] ?? habit_color_group($num));
            $mode = (string) ($habit['tracking_mode'] ?? 'months');
            $done = ($habit['log_status'] ?? null) === 'completed' || ($habit['log_status'] ?? null) === 'partial';
            $isSystem = !empty($habit['is_system']);
            $isWater = ($habit['habit_key'] ?? '') === HabitService::KEY_WATER;
            $isFruit = ($habit['habit_key'] ?? '') === HabitService::KEY_FRUIT;
            $isQty = $isWater || $isFruit || $mode === 'daily_qty';
            $friend = $habit['suggested_friend'] ?? null;
            if ($isQty) {
                $done = (float) ($habit['log_quantity'] ?? 0) + 0.0001 >= (float) ($habit['target_per_period'] ?? ($isFruit ? 3 : 2000));
            }
            $monthChecks = $habit['month_checks'] ?? MonthProgress::emptyMap();
            $monthsCsv = [];
            for ($m = 1; $m <= 12; $m++) {
                if (!empty($monthChecks[$m])) {
                    $monthsCsv[] = $m;
                }
            }
            ?>
            <li
                class="habit-row<?= $done ? ' is-done' : '' ?><?= $isSystem ? ' is-system' : '' ?>"
                data-color-group="<?= $group ?>"
                data-habit-id="<?= (int) $habit['id'] ?>"
                <?php if (!$isSystem): ?>
                data-edit-habit
                data-id="<?= (int) $habit['id'] ?>"
                data-name="<?= e((string) $habit['name']) ?>"
                data-description="<?= e((string) ($habit['description'] ?? '')) ?>"
                data-tracking-mode="<?= e($mode) ?>"
                data-target="<?= e((string) (float) ($habit['target_per_period'] ?? 12)) ?>"
                data-current="<?= e((string) (float) ($habit['current_value'] ?? 0)) ?>"
                data-unit="<?= e((string) ($habit['unit'] ?? 'unidades')) ?>"
                data-progress="<?= e((string) (int) ($habit['progress_percent'] ?? 0)) ?>"
                data-months="<?= e(implode(',', $monthsCsv)) ?>"
                data-frequency="<?= e((string) ($habit['frequency_type'] ?? 'daily')) ?>"
                role="button"
                tabindex="0"
                <?php endif; ?>
                data-is-system="<?= $isSystem ? '1' : '0' ?>"
            >
                <span class="habit-drag" data-drag-handle title="Arrastrar para reordenar" aria-label="Arrastrar para reordenar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
                </span>
                <span class="habit-num"><?= $num ?></span>
                <span class="habit-meta">
                    <span class="habit-name">
                        <?= e((string) $habit['name']) ?>
                        <?php if ($isSystem): ?><span class="habit-system-badge">Sistema</span><?php endif; ?>
                        <?php if ($friend && ($habit['habit_key'] ?? '') === HabitService::KEY_TALK_FRIEND): ?>
                            <span class="habit-friend-hint"><?= $isHabitToday ? 'Hoy' : 'Ese día' ?>: <?= e((string) $friend['name']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="muted small">
                        <?php if ($isSystem): ?>
                            Diario · lunes a lunes
                        <?php elseif ($mode === 'units'): ?>
                            <?= e(number_format((float) ($habit['current_value'] ?? 0), 0)) ?> /
                            <?= e(number_format((float) ($habit['target_per_period'] ?? 0), 0)) ?>
                            <?= e((string) ($habit['unit'] ?? 'unidades')) ?>
                            · <?= e(number_format((float) ($habit['progress_percent'] ?? 0), 0)) ?>%
                        <?php elseif ($mode === 'months'): ?>
                            Meses · <?= e(number_format((float) ($habit['progress_percent'] ?? 0), 0)) ?>%
                        <?php else: ?>
                            <?= e(match ((string) ($habit['frequency_type'] ?? 'daily')) {
                                'weekdays' => 'Días hábiles',
                                'custom_days' => 'Días personalizados',
                                'weekly' => 'Semanal',
                                'monthly' => 'Mensual',
                                default => 'Diario',
                            }) ?>
                        <?php endif; ?>
                    </span>
                </span>
                <?php if ($isFruit):
                    $returnPath = $habitReturnPath;
                    $todayDate = $habitDate;
                    include dirname(__DIR__) . '/partials/habit_fruit.php';
                    $todayDate = $realTodayDate;
                elseif ($isWater || $isQty):
                    $returnPath = $habitReturnPath;
                    $todayDate = $habitDate;
                    include dirname(__DIR__) . '/partials/habit_water.php';
                    $todayDate = $realTodayDate;
                elseif ($mode === 'units'): ?>
                    <form method="post" action="<?= e(form_action()) ?>" class="habit-toggle-form" data-lq-save onclick="event.stopPropagation()">
                        <?= csrf_field() ?>
                        <?= route_field('/habits/' . (int) $habit['id'] . '/units') ?>
                        <input type="hidden" name="delta" value="1">
                        <button type="submit" class="btn btn-ghost btn-sm" title="Sumar 1">+1</button>
                    </form>
                <?php elseif ($mode === 'months'): ?>
                    <span class="muted small"><?= e(number_format((float) ($habit['progress_percent'] ?? 0), 0)) ?>%</span>
                <?php endif; ?>
                <?php if (!$isQty): ?>
                <form method="post" action="<?= e(form_action()) ?>" class="habit-toggle-form" data-habit-toggle onclick="event.stopPropagation()">
                    <?= csrf_field() ?>
                    <?= route_field('/habits/log') ?>
                    <input type="hidden" name="habit_id" value="<?= (int) $habit['id'] ?>">
                    <input type="hidden" name="date" value="<?= e($habitDate) ?>">
                    <input type="hidden" name="status" value="<?= $done ? 'missed' : 'completed' ?>">
                    <input type="hidden" name="redirect" value="<?= e($habitReturnPath) ?>">
                    <?php if ($friend): ?>
                        <input type="hidden" name="friend_id" value="<?= (int) $friend['id'] ?>">
                    <?php endif; ?>
                    <label class="check-toggle" title="<?= $isHabitToday ? 'Marcar hecho hoy' : 'Marcar hecho ese día' ?>">
                        <input type="checkbox" <?= $done ? 'checked' : '' ?> aria-label="Marcar <?= e((string) $habit['name']) ?>">
                        <span></span>
                    </label>
                </form>
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
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear</button>
        </footer>
    </form>
</dialog>
