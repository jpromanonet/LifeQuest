<?php
/** @var list<array> $habits */
/** @var array|null $selected */
/** @var array $streak */
/** @var list<array> $recentLogs */
/** @var list<array> $areas */
/** @var string $todayDate */
/** @var array<int,bool> $selectedMonths */
/** @var array<int,string> $monthLabels */
/** @var int $habitYear */

$selectedMonths = $selectedMonths ?? MonthProgress::emptyMap();
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

<div class="habits-layout<?= $selected ? ' has-detail' : '' ?>">
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
                $isSelected = $selected && (int) $selected['id'] === (int) $habit['id'];
                ?>
                <li class="habit-row<?= $isSelected ? ' is-selected' : '' ?><?= $done ? ' is-done' : '' ?>" data-color-group="<?= $group ?>" data-habit-id="<?= (int) $habit['id'] ?>">
                    <a class="habit-main-link" href="<?= e(url('/habits?id=' . (int) $habit['id'])) ?>">
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
                    </a>
                    <?php if ($mode === 'daily'): ?>
                        <form method="post" action="<?= e(url('/habits/log')) ?>" class="habit-toggle-form" data-habit-toggle>
                            <?= csrf_field() ?>
                            <input type="hidden" name="habit_id" value="<?= (int) $habit['id'] ?>">
                            <input type="hidden" name="date" value="<?= e($todayDate) ?>">
                            <input type="hidden" name="status" value="<?= $done ? 'missed' : 'completed' ?>">
                            <input type="hidden" name="redirect" value="/habits<?= $selected ? '?id=' . (int) $selected['id'] : '' ?>">
                            <label class="check-toggle">
                                <input type="checkbox" <?= $done ? 'checked' : '' ?> aria-label="Marcar <?= e((string) $habit['name']) ?>">
                                <span></span>
                            </label>
                        </form>
                    <?php elseif ($mode === 'units'): ?>
                        <form method="post" action="<?= e(url('/habits/' . (int) $habit['id'] . '/units')) ?>" class="habit-toggle-form">
                            <?= csrf_field() ?>
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

    <?php if ($selected):
        $selMode = (string) ($selected['tracking_mode'] ?? 'months');
        ?>
        <aside class="card detail-panel">
            <div class="detail-head">
                <div class="habit-num lg" data-color-group="<?= (int) ($selected['color_group'] ?? 0) ?>"><?= (int) ($selected['display_number'] ?? 0) ?></div>
                <h2><?= e((string) $selected['name']) ?></h2>
                <p class="muted"><?= e((string) ($selected['area_name'] ?? 'Sin área')) ?> ·
                    <?php if ($selMode === 'units'): ?>Unidades<?php elseif ($selMode === 'months'): ?>Meses<?php else: ?>Diario<?php endif; ?>
                </p>
            </div>

            <?php if ($selMode === 'months'): ?>
                <div class="detail-block">
                    <div class="month-progress-head">
                        <span>Meses <?= (int) $habitYear ?></span>
                        <strong><?= e(number_format((float) ($selected['progress_percent'] ?? 0), 0)) ?>%</strong>
                    </div>
                    <p class="muted small">Tildá los meses en los que cumpliste este hábito.</p>
                    <div class="month-grid">
                        <?php foreach ($monthLabels as $num => $label):
                            $on = !empty($selectedMonths[$num]);
                            ?>
                            <form method="post" action="<?= e(url('/habits/' . (int) $selected['id'] . '/months')) ?>" class="month-chip<?= $on ? ' is-on' : '' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="year" value="<?= (int) $habitYear ?>">
                                <input type="hidden" name="month" value="<?= (int) $num ?>">
                                <button type="submit" style="all:unset;cursor:pointer;width:100%;text-align:center"><?= e($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($selMode === 'units'): ?>
                <div class="detail-block">
                    <div class="month-progress-head">
                        <span>Progreso</span>
                        <strong><?= e(number_format((float) ($selected['progress_percent'] ?? 0), 0)) ?>%</strong>
                    </div>
                    <p class="kpi-value" style="font-size:1.6rem;margin:8px 0">
                        <?= e(number_format((float) ($selected['current_value'] ?? 0), 0)) ?>
                        <span class="muted" style="font-size:1rem">/
                            <?= e(number_format((float) ($selected['target_per_period'] ?? 0), 0)) ?>
                            <?= e((string) ($selected['unit'] ?? 'unidades')) ?>
                        </span>
                    </p>
                    <div class="btn-row">
                        <form method="post" action="<?= e(url('/habits/' . (int) $selected['id'] . '/units')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delta" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">+1</button>
                        </form>
                        <form method="post" action="<?= e(url('/habits/' . (int) $selected['id'] . '/units')) ?>" class="btn-row" style="gap:8px">
                            <?= csrf_field() ?>
                            <input type="number" name="current_value" min="0" step="1" value="<?= e((string) (float) ($selected['current_value'] ?? 0)) ?>" style="width:90px">
                            <button type="submit" class="btn btn-ghost btn-sm">Fijar</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="split-stats">
                    <div>
                        <div class="kpi-label">Racha actual</div>
                        <div class="kpi-value"><?= (int) $streak['current_streak'] ?></div>
                    </div>
                    <div>
                        <div class="kpi-label">Mejor racha</div>
                        <div class="kpi-value"><?= (int) $streak['best_streak'] ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($selected['description'])): ?>
                <div class="detail-block">
                    <p><?= nl2br(e((string) $selected['description'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($selMode === 'daily'): ?>
                <div class="detail-block">
                    <div class="kpi-label">Últimos registros</div>
                    <ul class="log-list">
                        <?php if ($recentLogs === []): ?>
                            <li class="muted small">Sin registros aún.</li>
                        <?php endif; ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <li>
                                <span><?= e(format_date($log['log_date'], 'd M Y')) ?></span>
                                <span class="badge"><?= e((string) $log['status']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="detail-actions">
                <details>
                    <summary class="btn btn-ghost btn-sm">Editar</summary>
                    <form method="post" action="<?= e(form_action()) ?>" class="stack-form" style="margin-top:12px" data-lq-save>
                        <?= csrf_field() ?>
                        <?= route_field('/habits/' . (int) $selected['id']) ?>
                        <label class="field"><span>Nombre</span><input type="text" name="name" value="<?= e((string) $selected['name']) ?>" required></label>
                        <label class="field"><span>Descripción</span><textarea name="description" rows="2"><?= e((string) ($selected['description'] ?? '')) ?></textarea></label>
                        <label class="field">
                            <span>Área</span>
                            <select name="area_id">
                                <option value="">Sin área</option>
                                <?php foreach ($areas as $area): ?>
                                    <option value="<?= (int) $area['id'] ?>" <?= (int) ($selected['area_id'] ?? 0) === (int) $area['id'] ? 'selected' : '' ?>><?= e((string) $area['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Medición</span>
                            <select name="tracking_mode">
                                <option value="months" <?= $selMode === 'months' ? 'selected' : '' ?>>Por meses (12)</option>
                                <option value="units" <?= $selMode === 'units' ? 'selected' : '' ?>>Por unidades</option>
                                <option value="daily" <?= $selMode === 'daily' ? 'selected' : '' ?>>Diario</option>
                            </select>
                        </label>
                        <div class="form-grid-2">
                            <label class="field"><span>Meta</span><input type="number" name="target_per_period" min="1" value="<?= e((string) (int) ($selected['target_per_period'] ?? 1)) ?>"></label>
                            <label class="field"><span>Unidad</span><input type="text" name="unit" value="<?= e((string) ($selected['unit'] ?? 'unidad')) ?>"></label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                    </form>
                </details>
                <form method="post" action="<?= e(form_action()) ?>" data-lq-save>
                    <?= csrf_field() ?>
                    <?= route_field('/habits/' . (int) $selected['id'] . '/archive') ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Archivar</button>
                </form>
                <form method="post" action="<?= e(form_action()) ?>" data-lq-save onsubmit="return confirm('¿Eliminar este hábito?');">
                    <?= csrf_field() ?>
                    <?= route_field('/habits/' . (int) $selected['id'] . '/delete') ?>
                    <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                </form>
            </div>
        </aside>
    <?php endif; ?>
</div>

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
