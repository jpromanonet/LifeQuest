<?php
/** @var int $year */
/** @var list<int> $availableYears */
/** @var array $filters */
/** @var list<array{area:array,goals:list}> $grouped */
/** @var array<string,int> $counts */
/** @var int $atRisk */
/** @var list<array> $areas */
/** @var array|null $selected */

$activeCount = (int) ($counts['active'] ?? 0);
$completedCount = (int) ($counts['completed'] ?? 0);
$totalYearCount = array_sum($counts);
$completionPct = $totalYearCount > 0 ? round(($completedCount / $totalYearCount) * 100, 1) : 0.0;
$q = (string) ($filters['q'] ?? '');
$availableYears = !empty($availableYears) ? $availableYears : [$year];
$currentYear = (int) now_local()->format('Y');
// Saltamos al año cargado más cercano: un año ±1 que fue eliminado se recrearía al visitarlo.
$earlier = array_filter($availableYears, static fn (int $y): bool => $y < $year);
$later = array_filter($availableYears, static fn (int $y): bool => $y > $year);
$prevYear = $earlier !== [] ? max($earlier) : null;
$nextYear = $later !== [] ? min($later) : null;
?>
<section class="page-header with-actions">
    <div>
        <h1>Objetivos</h1>
        <p class="muted">Planes del año · educación, trabajo, marca personal, finanzas, salud y proyectos</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= e(url('/books?year=' . $year)) ?>">Libros</a>
        <button type="button" class="btn btn-ghost" data-open-modal="addYearModal">+ Año</button>
        <button type="button" class="btn btn-primary" data-open-modal="goalCreateModal">+ Nuevo objetivo</button>
        <?php if ($year !== $currentYear): ?>
            <form method="post" action="<?= e(form_action()) ?>" class="year-delete-form" data-reset-scroll data-lq-save onsubmit="return confirm('¿Eliminar el año <?= (int) $year ?>?\n\nSe archivan sus <?= (int) $totalYearCount ?> objetivos.\nLa biblioteca y el horizonte no se tocan.');">
                <?= csrf_field() ?>
                <?= route_field('/goals/years/' . (int) $year . '/delete') ?>
                <button type="submit" class="btn btn-danger">Eliminar año</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<div class="year-nav">
    <?php if ($prevYear): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/goals?year=' . $prevYear)) ?>">← <?= $prevYear ?></a>
    <?php else: ?>
        <span class="year-nav-spacer"></span>
    <?php endif; ?>
    <form method="get" action="<?= e(form_action()) ?>" class="year-select-form">
        <?= route_field('/goals') ?>
        <label class="field year-select-field">
            <span class="sr-only">Año</span>
            <select name="year" onchange="this.form.submit()" aria-label="Elegir año">
                <?php foreach ($availableYears as $y): ?>
                    <option value="<?= (int) $y ?>" <?= (int) $y === (int) $year ? 'selected' : '' ?>><?= (int) $y ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <?php if ($nextYear): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/goals?year=' . $nextYear)) ?>"><?= $nextYear ?> →</a>
    <?php else: ?>
        <span class="year-nav-spacer"></span>
    <?php endif; ?>
</div>

<form method="get" action="<?= e(form_action()) ?>" class="filters-bar">
    <?= route_field('/goals') ?>
    <input type="hidden" name="year" value="<?= (int) $year ?>">
    <label>
        <span class="sr-only">Área</span>
        <select name="area">
            <option value="">Todas las áreas</option>
            <?php foreach ($areas as $area): ?>
                <option value="<?= (int) $area['id'] ?>" <?= (string) ($filters['area_id'] ?? '') === (string) $area['id'] ? 'selected' : '' ?>>
                    <?= e((string) $area['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span class="sr-only">Estado</span>
        <select name="status">
            <option value="">Todos los estados</option>
            <?php foreach (['idea','planned','active','paused','completed','cancelled','archived'] as $st): ?>
                <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span class="sr-only">Prioridad</span>
        <select name="priority">
            <option value="">Prioridad</option>
            <?php foreach (['low','medium','high','critical'] as $pr): ?>
                <option value="<?= $pr ?>" <?= ($filters['priority'] ?? '') === $pr ? 'selected' : '' ?>><?= e(priority_label($pr)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="filter-search">
        <span class="sr-only">Buscar</span>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar objetivos">
    </label>
    <button type="submit" class="btn btn-ghost">Filtrar</button>
</form>

<section class="goals-completion-bar card">
    <div class="goals-completion-head">
        <span class="kpi-label">Completitud <?= (int) $year ?></span>
        <strong><?= e(number_format($completionPct, 0)) ?>%</strong>
    </div>
    <div class="progress goals-completion-track">
        <span style="width:<?= e((string) min(100, $completionPct)) ?>%"></span>
    </div>
    <div class="muted small"><?= $completedCount ?> / <?= (int) $totalYearCount ?> objetivos completados</div>
</section>

<section class="kpi-grid kpi-grid-4">
    <article class="card kpi-card kpi-card--lavender">
        <div class="kpi-label">Totales <?= (int) $year ?></div>
        <div class="kpi-value"><?= (int) $totalYearCount ?></div>
        <div class="muted small">Objetivos del año</div>
    </article>
    <article class="card kpi-card kpi-card--primary">
        <div class="kpi-label">Activos</div>
        <div class="kpi-value"><?= $activeCount ?></div>
        <div class="muted small">En progreso</div>
    </article>
    <article class="card kpi-card kpi-card--peach">
        <div class="kpi-label">En riesgo</div>
        <div class="kpi-value"><?= (int) $atRisk ?></div>
        <div class="muted small">Requieren atención</div>
    </article>
    <article class="card kpi-card kpi-card--mint">
        <div class="kpi-label">Completados</div>
        <div class="kpi-value"><?= $completedCount ?></div>
        <div class="muted small">Este año</div>
    </article>
</section>

<div class="goals-main">
    <?php if ($grouped === []): ?>
        <div class="card empty-state">No hay áreas configuradas para este año.</div>
    <?php endif; ?>
    <?php foreach ($grouped as $section):
        $area = $section['area'];
        $sectionGoals = $section['goals'];
        ?>
        <section class="card goal-section">
            <header class="section-head">
                <span class="area-dot" style="background:<?= e((string) ($area['color'] ?? '#7C83E1')) ?>"></span>
                <h2><?= e((string) $area['name']) ?></h2>
                <span class="muted small"><?= count($sectionGoals) ?></span>
            </header>
            <?php if ($sectionGoals === []): ?>
                <p class="muted small empty-section">Sin objetivos en esta área para <?= (int) $year ?>.</p>
            <?php else: ?>
            <div class="goal-table" data-sortable>
                <div class="goal-table-head" role="row">
                    <button type="button" class="sort-btn" data-sort="title" aria-label="Ordenar por objetivo">Objetivo</button>
                    <button type="button" class="sort-btn" data-sort="progress" data-sort-type="number" aria-label="Ordenar por progreso">Progreso</button>
                    <button type="button" class="sort-btn" data-sort="status" aria-label="Ordenar por estado">Estado</button>
                    <button type="button" class="sort-btn" data-sort="due" data-sort-type="date" aria-label="Ordenar por vencimiento">Vencimiento</button>
                    <button type="button" class="sort-btn" data-sort="next" aria-label="Ordenar por próxima acción">Próxima acción</button>
                </div>
                <?php foreach ($sectionGoals as $goal):
                    $mode = (string) ($goal['progress_mode'] ?? 'months');
                    $monthChecks = $goal['month_checks'] ?? MonthProgress::emptyMap();
                    $monthsCsv = [];
                    for ($m = 1; $m <= 12; $m++) {
                        if (!empty($monthChecks[$m])) {
                            $monthsCsv[] = $m;
                        }
                    }
                    $binaryDone = $mode === 'binary' && ((float) $goal['progress_percent'] >= 100 || ($goal['status'] ?? '') === 'completed');
                    $isBooks = (string) ($goal['external_system'] ?? '') === 'books';
                    $progressSort = $mode === 'binary' ? ($binaryDone ? 100 : 0) : (float) ($goal['progress_percent'] ?? 0);
                    ?>
                    <?php if ($isBooks): ?>
                    <a
                        class="goal-row goal-row-btn goal-row-link"
                        href="<?= e(url('/books?year=' . (int) ($goal['period_year'] ?? $year))) ?>"
                        title="Ir a la biblioteca del año"
                        data-sort-title="<?= e(mb_strtolower((string) $goal['title'])) ?>"
                        data-sort-progress="<?= e((string) $progressSort) ?>"
                        data-sort-status="<?= e((string) $goal['status']) ?>"
                        data-sort-due="<?= e((string) ($goal['due_date'] ?? '')) ?>"
                        data-sort-next="<?= e(mb_strtolower((string) ($goal['next_action'] ?? ''))) ?>"
                    >
                    <?php else: ?>
                    <div
                        role="button"
                        tabindex="0"
                        class="goal-row goal-row-btn"
                        data-edit-goal
                        data-id="<?= (int) $goal['id'] ?>"
                        data-title="<?= e((string) $goal['title']) ?>"
                        data-description="<?= e((string) ($goal['description'] ?? '')) ?>"
                        data-area-id="<?= e((string) ($goal['area_id'] ?? '')) ?>"
                        data-period-year="<?= e((string) ($goal['period_year'] ?? $year)) ?>"
                        data-status="<?= e((string) $goal['status']) ?>"
                        data-priority="<?= e((string) $goal['priority']) ?>"
                        data-progress="<?= e((string) (int) $goal['progress_percent']) ?>"
                        data-progress-mode="<?= e($mode) ?>"
                        data-target-value="<?= e((string) (float) ($goal['target_value'] ?? 0)) ?>"
                        data-current-value="<?= e((string) (float) ($goal['current_value'] ?? 0)) ?>"
                        data-unit="<?= e((string) ($goal['unit'] ?? '')) ?>"
                        data-months="<?= e(implode(',', $monthsCsv)) ?>"
                        data-due-date="<?= e((string) ($goal['due_date'] ?? '')) ?>"
                        data-next-action="<?= e((string) ($goal['next_action'] ?? '')) ?>"
                        data-success-criteria="<?= e((string) ($goal['success_criteria'] ?? '')) ?>"
                        data-sort-title="<?= e(mb_strtolower((string) $goal['title'])) ?>"
                        data-sort-progress="<?= e((string) $progressSort) ?>"
                        data-sort-status="<?= e((string) $goal['status']) ?>"
                        data-sort-due="<?= e((string) ($goal['due_date'] ?? '')) ?>"
                        data-sort-next="<?= e(mb_strtolower((string) ($goal['next_action'] ?? ''))) ?>"
                    >
                    <?php endif; ?>
                        <span>
                            <strong><?= e((string) $goal['title']) ?></strong>
                            <?php if ($isBooks): ?>
                                <span class="muted small block-ellipsis">Objetivo fijo · abre la biblioteca →</span>
                            <?php elseif (!empty($goal['description'])): ?>
                                <span class="muted small block-ellipsis"><?= e((string) $goal['description']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span>
                            <?php if ($mode === 'binary'): ?>
                                <span class="badge <?= $binaryDone ? 'status-completed' : 'status-planned' ?>">
                                    <?= $binaryDone ? 'Hecho' : 'Pendiente' ?>
                                </span>
                                <span class="small"><?= $binaryDone ? '100%' : '0%' ?></span>
                            <?php else: ?>
                                <span class="progress"><span style="width:<?= e((string) min(100, (float) $goal['progress_percent'])) ?>%"></span></span>
                                <span class="small"><?= e(number_format((float) $goal['progress_percent'], 0)) ?>%</span>
                                <?php if ($mode === 'months'): ?>
                                    <span class="muted tiny"><?= (int) ($goal['months_checked'] ?? 0) ?>/12</span>
                                <?php elseif ($mode === 'quantity'): ?>
                                    <span class="muted tiny">
                                        <?= e(number_format((float) ($goal['current_value'] ?? 0), 0)) ?>/<?= e(number_format((float) ($goal['target_value'] ?? 0), 0)) ?>
                                        <?= e((string) ($goal['unit'] ?? 'unidades')) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                        <span><span class="badge status-<?= e((string) $goal['status']) ?>"><?= e(status_label((string) $goal['status'])) ?></span></span>
                        <span class="muted small"><?= e(format_date($goal['due_date'] ?? null, 'd M Y')) ?></span>
                        <span class="small"><?= e((string) ($goal['next_action'] ?? '—')) ?></span>
                    <?= $isBooks ? '</a>' : '</div>' ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<dialog class="modal" id="goalCreateModal">
    <form method="post" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save novalidate>
        <?= csrf_field() ?>
        <?= route_field('/goals') ?>
        <header class="modal-head">
            <h2>Nuevo objetivo</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Título</span>
            <input type="text" name="title" required maxlength="255">
        </label>
        <label class="field">
            <span>Descripción</span>
            <textarea name="description" rows="3"></textarea>
        </label>
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
                <span>Año</span>
                <input type="number" name="period_year" value="<?= (int) $year ?>" min="2020" max="2040">
            </label>
            <label class="field">
                <span>Estado</span>
                <select name="status">
                    <?php foreach (['planned','active','idea','paused'] as $st): ?>
                        <option value="<?= $st ?>"><?= e(status_label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Prioridad</span>
                <select name="priority">
                    <?php foreach (['low','medium','high','critical'] as $pr): ?>
                        <option value="<?= $pr ?>" <?= $pr === 'medium' ? 'selected' : '' ?>><?= e(priority_label($pr)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Vencimiento</span>
                <input type="date" name="due_date">
            </label>
            <label class="field">
                <span>Próxima acción</span>
                <input type="text" name="next_action">
            </label>
            <label class="field">
                <span>Tipo de progreso</span>
                <select name="progress_mode" id="createProgressMode">
                    <option value="months">Meses (12)</option>
                    <option value="quantity">Unidades</option>
                    <option value="binary">Sí / No (objetivo)</option>
                </select>
            </label>
            <label class="field" id="createTargetField" hidden>
                <span>Meta (unidades)</span>
                <input type="number" name="target_value" min="1" step="1" value="12">
            </label>
        </div>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear</button>
        </footer>
    </form>
</dialog>

<dialog class="modal" id="addYearModal">
    <form method="post" action="<?= e(form_action()) ?>" class="stack-form" data-reset-scroll data-lq-save>
        <?= csrf_field() ?>
        <?= route_field('/goals/years') ?>
        <header class="modal-head">
            <h2>Agregar año</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Año</span>
            <input type="number" name="year" min="2000" max="2100" value="<?= (int) $year + 1 ?>" required>
        </label>
        <p class="muted small">Se crea el año con todas las áreas vacías listas para llenar.</p>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear año</button>
        </footer>
    </form>
</dialog>

<dialog class="modal" id="goalEditModal"
    <?php
    $selectedMonths = $selectedMonths ?? MonthProgress::emptyMap();
    $monthLabels = $monthLabels ?? MonthProgress::MONTH_LABELS;
    $selectedMonthsCsv = [];
    for ($m = 1; $m <= 12; $m++) {
        if (!empty($selectedMonths[$m])) {
            $selectedMonthsCsv[] = $m;
        }
    }
    ?>
    <?php if ($selected): ?>
        data-id="<?= (int) $selected['id'] ?>"
        data-title="<?= e((string) $selected['title']) ?>"
        data-description="<?= e((string) ($selected['description'] ?? '')) ?>"
        data-area-id="<?= e((string) ($selected['area_id'] ?? '')) ?>"
        data-period-year="<?= e((string) ($selected['period_year'] ?? $year)) ?>"
        data-status="<?= e((string) $selected['status']) ?>"
        data-priority="<?= e((string) $selected['priority']) ?>"
        data-progress="<?= e((string) (int) $selected['progress_percent']) ?>"
        data-progress-mode="<?= e((string) ($selected['progress_mode'] ?? 'months')) ?>"
        data-target-value="<?= e((string) (float) ($selected['target_value'] ?? 0)) ?>"
        data-current-value="<?= e((string) (float) ($selected['current_value'] ?? 0)) ?>"
        data-unit="<?= e((string) ($selected['unit'] ?? '')) ?>"
        data-months="<?= e(implode(',', $selectedMonthsCsv)) ?>"
        data-due-date="<?= e((string) ($selected['due_date'] ?? '')) ?>"
        data-next-action="<?= e((string) ($selected['next_action'] ?? '')) ?>"
        data-success-criteria="<?= e((string) ($selected['success_criteria'] ?? '')) ?>"
    <?php endif; ?>
>
    <form method="post" id="goalEditForm" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="editRoute" value="/goals/0">
        <header class="modal-head">
            <h2>Editar objetivo</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Título</span>
            <input type="text" name="title" id="editTitle" required maxlength="255">
        </label>
        <label class="field">
            <span>Descripción</span>
            <textarea name="description" id="editDescription" rows="3"></textarea>
        </label>
        <div class="form-grid-2">
            <label class="field">
                <span>Área</span>
                <select name="area_id" id="editAreaId">
                    <option value="">Sin área</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?= (int) $area['id'] ?>"><?= e((string) $area['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Año</span>
                <input type="number" name="period_year" id="editPeriodYear" min="2020" max="2040">
            </label>
            <label class="field">
                <span>Estado</span>
                <select name="status" id="editStatus">
                    <?php foreach (['idea','planned','active','paused','completed','cancelled','archived'] as $st): ?>
                        <option value="<?= $st ?>"><?= e(status_label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Prioridad</span>
                <select name="priority" id="editPriority">
                    <?php foreach (['low','medium','high','critical'] as $pr): ?>
                        <option value="<?= $pr ?>"><?= e(priority_label($pr)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Vencimiento</span>
                <input type="date" name="due_date" id="editDueDate">
            </label>
            <label class="field">
                <span>Próxima acción</span>
                <input type="text" name="next_action" id="editNextAction">
            </label>
            <label class="field">
                <span>Tipo de progreso</span>
                <select name="progress_mode" id="editProgressMode">
                    <option value="months">Meses (12)</option>
                    <option value="quantity">Unidades</option>
                    <option value="binary">Sí / No (objetivo)</option>
                </select>
            </label>
        </div>
        <div class="field" id="editBinaryBlock" hidden>
            <div class="month-progress-head">
                <span>Objetivo sí / no</span>
                <strong id="editBinaryLabel">0%</strong>
            </div>
            <p class="muted small">0% hasta marcarlo como hecho → 100%.</p>
            <button type="button" class="btn btn-primary" id="editBinaryToggle">Marcar como hecho</button>
        </div>
        <div class="field" id="editUnitsBlock" hidden>
            <div class="month-progress-head">
                <span>Progreso en <span id="editUnitsUnit">unidades</span></span>
                <strong id="editUnitsLabel">0%</strong>
            </div>
            <p class="muted small">Cargá cuántas unidades llevás. El porcentaje se calcula sobre la meta.</p>
            <div class="units-grid units-grid--2">
                <label class="field">
                    <span>Hechas</span>
                    <input type="number" name="current_value" id="editCurrentValue" min="0" step="1" value="0">
                </label>
                <label class="field">
                    <span>Meta</span>
                    <input type="number" name="target_value" id="editTargetValue" min="1" step="1" value="12">
                </label>
            </div>
            <div class="btn-row" style="margin-top:10px">
                <button type="button" class="btn btn-ghost btn-sm" id="editUnitsPlus">+1</button>
                <button type="button" class="btn btn-primary btn-sm" id="editUnitsSave">Guardar unidades</button>
            </div>
        </div>
        <div class="field" id="editMonthsBlock">
            <div class="month-progress-head">
                <span>Meses del año</span>
                <strong id="editProgressLabel">0%</strong>
            </div>
            <p class="muted small">Tildá los meses en los que avanzaste. El porcentaje se calcula solo (meses / 12).</p>
            <div class="month-grid" id="editMonthGrid" data-goal-id="">
                <?php foreach ($monthLabels as $num => $label): ?>
                    <label class="month-chip">
                        <input type="checkbox" data-month="<?= (int) $num ?>" value="<?= (int) $num ?>">
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="editMonthsCsv" value="">
        </div>
        <label class="field">
            <span>Criterio de éxito</span>
            <textarea name="success_criteria" id="editSuccessCriteria" rows="2"></textarea>
        </label>
        <footer class="modal-foot modal-foot-split">
            <button type="submit" form="goalDeleteForm" class="btn btn-danger" onclick="return confirm('¿Eliminar este objetivo?');">Eliminar</button>
            <div class="btn-row">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </footer>
    </form>
    <form method="post" id="goalDeleteForm" action="<?= e(form_action()) ?>" hidden data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="deleteRoute" value="/goals/0/delete">
        <input type="hidden" name="year" id="goalDeleteYear" value="<?= (int) $year ?>">
    </form>
    <form method="post" id="goalMonthToggleForm" action="<?= e(form_action()) ?>" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="monthRoute" value="/goals/0/months">
        <input type="hidden" name="month" id="monthValue" value="">
    </form>
    <form method="post" id="goalBinaryToggleForm" action="<?= e(form_action()) ?>" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="binaryRoute" value="/goals/0/binary">
    </form>
    <form method="post" id="goalUnitsForm" action="<?= e(form_action()) ?>" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="unitsRoute" value="/goals/0/units">
    </form>
</dialog>
