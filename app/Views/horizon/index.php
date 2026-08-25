<?php
/** @var list<array{area:array,goals:list}> $grouped */
/** @var list<array> $areas */
/** @var array|null $selected */
/** @var array<string,int> $totals */
/** @var array{area_id:int,done:string} $filters */

$filters = $filters ?? ['area_id' => 0, 'done' => ''];
$hasFilters = ((int) ($filters['area_id'] ?? 0) > 0) || (($filters['done'] ?? '') !== '');

$horizonDone = static function (array $goal): bool {
    return (float) ($goal['progress_percent'] ?? 0) >= 100 || ($goal['status'] ?? '') === 'completed';
};
?>
<section class="page-header with-actions">
    <div>
        <h1>Horizontes</h1>
        <p class="muted">Objetivos mayores de vida · se marcan como logrados, sin meses ni porcentajes</p>
    </div>
    <button type="button" class="btn btn-primary" data-open-modal="horizonModal">+ Nuevo horizonte</button>
</section>

<?php
$horizonTotal = (int) ($totals['all'] ?? 0);
$horizonAchieved = (int) ($totals['achieved'] ?? 0);
$horizonPct = $horizonTotal > 0 ? round(($horizonAchieved / $horizonTotal) * 100) : 0;
?>
<section class="goals-completion-bar card">
    <div class="goals-completion-head">
        <span class="kpi-label">Completitud de horizontes</span>
        <strong><?= (int) $horizonPct ?>%</strong>
    </div>
    <div class="progress goals-completion-track">
        <span style="width:<?= (int) min(100, $horizonPct) ?>%"></span>
    </div>
    <div class="muted small"><?= $horizonAchieved ?> / <?= $horizonTotal ?> horizontes logrados</div>
</section>

<section class="kpi-grid kpi-grid-3">
    <article class="card kpi-card kpi-card--lavender">
        <div class="kpi-label">Totales</div>
        <div class="kpi-value"><?= (int) ($totals['all'] ?? 0) ?></div>
        <div class="muted small">Visión de largo plazo</div>
    </article>
    <article class="card kpi-card kpi-card--primary">
        <div class="kpi-label">En camino</div>
        <div class="kpi-value"><?= (int) ($totals['in_progress'] ?? 0) ?></div>
        <div class="muted small">Todavía no logrados</div>
    </article>
    <article class="card kpi-card kpi-card--mint">
        <div class="kpi-label">Logrados</div>
        <div class="kpi-value"><?= (int) ($totals['achieved'] ?? 0) ?></div>
        <div class="muted small">Completados</div>
    </article>
</section>

<form method="get" action="<?= e(form_action()) ?>" class="filters-bar">
    <?= route_field('/horizon') ?>
    <label>
        <span class="sr-only">Área</span>
        <select name="area" onchange="this.form.submit()" aria-label="Filtrar por área">
            <option value="">Todas las áreas</option>
            <?php foreach ($areas as $area): ?>
                <option value="<?= (int) $area['id'] ?>" <?= (int) ($filters['area_id'] ?? 0) === (int) $area['id'] ? 'selected' : '' ?>>
                    <?= e((string) $area['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span class="sr-only">Logro</span>
        <select name="done" onchange="this.form.submit()" aria-label="Filtrar por logro">
            <option value="" <?= ($filters['done'] ?? '') === '' ? 'selected' : '' ?>>Todos (logro)</option>
            <option value="no" <?= ($filters['done'] ?? '') === 'no' ? 'selected' : '' ?>>En camino</option>
            <option value="yes" <?= ($filters['done'] ?? '') === 'yes' ? 'selected' : '' ?>>Logrados</option>
        </select>
    </label>
    <?php if ($hasFilters): ?>
        <a class="btn btn-ghost" href="<?= e(url('/horizon')) ?>">Limpiar</a>
    <?php endif; ?>
</form>

<div class="goals-main">
    <?php if ($grouped === []): ?>
        <div class="card empty-state">
            <?php if ($hasFilters): ?>
                No hay horizontes con ese filtro.
                <a class="text-link" href="<?= e(url('/horizon')) ?>">Ver todos</a>
            <?php else: ?>
                Todavía no hay horizontes. Creá el primero.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($grouped as $section):
        $area = $section['area'];
        $goals = $section['goals'];
        ?>
        <section class="card goal-section">
            <header class="section-head">
                <span class="area-dot" style="background:<?= e((string) ($area['color'] ?? '#7C83E1')) ?>"></span>
                <h2><?= e((string) $area['name']) ?></h2>
                <span class="muted small"><?= count($goals) ?></span>
            </header>
            <?php if ($goals === []): ?>
                <p class="muted small empty-section">Sin horizontes en esta área.</p>
            <?php else: ?>
                <div class="goal-table goal-table--horizon" data-sortable>
                    <div class="goal-table-head" role="row">
                        <button type="button" class="sort-btn" data-sort="title" aria-label="Ordenar por horizonte">Horizonte</button>
                        <button type="button" class="sort-btn" data-sort="done" data-sort-type="number" aria-label="Ordenar por logro">Logro</button>
                        <button type="button" class="sort-btn" data-sort="status" aria-label="Ordenar por estado">Estado</button>
                        <button type="button" class="sort-btn" data-sort="next" aria-label="Ordenar por próxima acción">Próxima acción</button>
                    </div>
                    <?php foreach ($goals as $goal):
                        $done = $horizonDone($goal);
                        ?>
                        <div
                            role="button"
                            tabindex="0"
                            class="goal-row goal-row-btn"
                            data-edit-horizon
                            data-id="<?= (int) $goal['id'] ?>"
                            data-title="<?= e((string) $goal['title']) ?>"
                            data-description="<?= e((string) ($goal['description'] ?? '')) ?>"
                            data-area-id="<?= e((string) ($goal['area_id'] ?? '')) ?>"
                            data-status="<?= e((string) $goal['status']) ?>"
                            data-priority="<?= e((string) ($goal['priority'] ?? 'medium')) ?>"
                            data-done="<?= $done ? '1' : '0' ?>"
                            data-next-action="<?= e((string) ($goal['next_action'] ?? '')) ?>"
                            data-sort-title="<?= e(mb_strtolower((string) $goal['title'])) ?>"
                            data-sort-done="<?= $done ? '1' : '0' ?>"
                            data-sort-status="<?= e((string) $goal['status']) ?>"
                            data-sort-next="<?= e(mb_strtolower((string) ($goal['next_action'] ?? ''))) ?>"
                        >
                            <span>
                                <strong><?= e((string) $goal['title']) ?></strong>
                                <?php if (!empty($goal['description'])): ?>
                                    <span class="muted small block-ellipsis"><?= e((string) $goal['description']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span>
                                <span class="badge <?= $done ? 'status-completed' : 'status-planned' ?>" data-horizon-flag>
                                    <?= $done ? 'Logrado' : 'En camino' ?>
                                </span>
                            </span>
                            <span><span class="badge status-<?= e((string) $goal['status']) ?>"><?= e(status_label((string) $goal['status'])) ?></span></span>
                            <span class="small"><?= e((string) ($goal['next_action'] ?? '—')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<dialog class="modal" id="horizonEditModal"
    <?php if ($selected): ?>
        data-id="<?= (int) $selected['id'] ?>"
        data-title="<?= e((string) $selected['title']) ?>"
        data-description="<?= e((string) ($selected['description'] ?? '')) ?>"
        data-area-id="<?= e((string) ($selected['area_id'] ?? '')) ?>"
        data-status="<?= e((string) $selected['status']) ?>"
        data-priority="<?= e((string) ($selected['priority'] ?? 'medium')) ?>"
        data-done="<?= $horizonDone($selected) ? '1' : '0' ?>"
        data-next-action="<?= e((string) ($selected['next_action'] ?? '')) ?>"
    <?php endif; ?>
>
    <form method="post" id="horizonEditForm" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="horizonEditRoute" value="/horizon/0">
        <input type="hidden" name="filter_area" value="<?= (int) ($filters['area_id'] ?? 0) ?>">
        <input type="hidden" name="filter_done" value="<?= e((string) ($filters['done'] ?? '')) ?>">
        <header class="modal-head">
            <h2>Editar horizonte</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Título</span>
            <input type="text" name="title" id="horizonEditTitle" required maxlength="255">
        </label>
        <label class="field">
            <span>Área de horizonte</span>
            <select name="area_id" id="horizonEditAreaId" required>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= (int) $area['id'] ?>"><?= e((string) $area['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Estado</span>
            <select name="status" id="horizonEditStatus">
                <?php foreach (['planned','active','completed','paused','cancelled','idea'] as $st): ?>
                    <option value="<?= $st ?>"><?= e(status_label($st)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Descripción</span>
            <textarea name="description" id="horizonEditDescription" rows="3"></textarea>
        </label>
        <label class="field">
            <span>Próxima acción</span>
            <input type="text" name="next_action" id="horizonEditNextAction">
        </label>
        <div class="field" id="horizonBinaryBlock" data-goal-id="">
            <div class="month-progress-head">
                <span>Logro</span>
                <strong id="horizonProgressLabel">En camino</strong>
            </div>
            <p class="muted small">Los horizontes son de largo plazo: no se miden por meses. Marcalo cuando lo consideres logrado.</p>
            <button type="button" class="btn btn-primary" id="horizonBinaryToggle" data-done="0">Marcar como logrado</button>
        </div>
        <footer class="modal-foot modal-foot-split">
            <button type="submit" form="horizonDeleteForm" class="btn btn-danger" onclick="return confirm('¿Eliminar este horizonte?');">Eliminar</button>
            <div class="btn-row">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </footer>
    </form>
    <form method="post" id="horizonDeleteForm" action="<?= e(form_action()) ?>" hidden data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="horizonDeleteRoute" value="/horizon/0/delete">
        <input type="hidden" name="filter_area" value="<?= (int) ($filters['area_id'] ?? 0) ?>">
        <input type="hidden" name="filter_done" value="<?= e((string) ($filters['done'] ?? '')) ?>">
    </form>
    <form method="post" id="horizonBinaryToggleForm" action="<?= e(form_action()) ?>" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="horizonBinaryRoute" value="/goals/0/binary">
    </form>
</dialog>

<dialog class="modal" id="horizonModal">
    <form method="post" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save>
        <?= csrf_field() ?>
        <?= route_field('/horizon') ?>
        <input type="hidden" name="filter_area" value="<?= (int) ($filters['area_id'] ?? 0) ?>">
        <input type="hidden" name="filter_done" value="<?= e((string) ($filters['done'] ?? '')) ?>">
        <header class="modal-head">
            <h2>Nuevo horizonte</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Título</span>
            <input type="text" name="title" required maxlength="255" placeholder="Ej. Tener vivienda propia">
        </label>
        <label class="field">
            <span>Área de horizonte</span>
            <select name="area_id" required>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= (int) $area['id'] ?>"><?= e((string) $area['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Descripción</span>
            <textarea name="description" rows="3"></textarea>
        </label>
        <div class="form-grid-2">
            <label class="field">
                <span>Estado</span>
                <select name="status">
                    <?php foreach (['planned','active','idea'] as $st): ?>
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
        </div>
        <label class="field">
            <span>Próxima acción</span>
            <input type="text" name="next_action">
        </label>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear</button>
        </footer>
    </form>
</dialog>
