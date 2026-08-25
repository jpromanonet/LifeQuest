<?php
/** @var array $selected */
/** @var list<array> $selectedMilestones */
/** @var list<array> $selectedActions */
$selectedMilestones = $selectedMilestones ?? [];
$selectedActions = $selectedActions ?? [];
?>
<div class="detail-head">
    <h2><?= e((string) $selected['title']) ?></h2>
    <p class="muted"><?= e((string) ($selected['area_name'] ?? 'Sin área')) ?></p>
    <div class="badge-row">
        <span class="badge status-<?= e((string) $selected['status']) ?>"><?= e(status_label((string) $selected['status'])) ?></span>
        <span class="badge"><?= e(priority_label((string) $selected['priority'])) ?></span>
        <?php if (!empty($selected['due_date'])): ?>
            <span class="muted small">Vence <?= e(format_date($selected['due_date'], 'd M Y')) ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="detail-block">
    <div class="kpi-label">Progreso</div>
    <div class="kpi-value"><?= e(number_format((float) $selected['progress_percent'], 0)) ?>%</div>
    <div class="progress"><span style="width:<?= e((string) min(100, (float) $selected['progress_percent'])) ?>%"></span></div>
    <form method="post" action="<?= e(url('/goals/' . (int) $selected['id'] . '/progress')) ?>" class="inline-progress">
        <?= csrf_field() ?>
        <input type="number" name="progress_percent" min="0" max="100" step="1" value="<?= e((string) (int) $selected['progress_percent']) ?>" aria-label="Porcentaje">
        <button type="submit" class="btn btn-ghost btn-sm">Actualizar</button>
    </form>
</div>

<?php if (!empty($selected['next_action'])): ?>
    <div class="detail-block">
        <div class="kpi-label">Próxima acción</div>
        <p><?= e((string) $selected['next_action']) ?></p>
    </div>
<?php endif; ?>

<?php if (!empty($selected['description'])): ?>
    <div class="detail-block">
        <div class="kpi-label">Descripción</div>
        <p><?= nl2br(e((string) $selected['description'])) ?></p>
    </div>
<?php endif; ?>

<?php if ($selectedMilestones !== []): ?>
    <div class="detail-block">
        <div class="kpi-label">Hitos</div>
        <ul class="checklist">
            <?php foreach ($selectedMilestones as $m): ?>
                <li class="<?= (int) $m['is_completed'] ? 'is-done' : '' ?>">
                    <?= e((string) $m['title']) ?>
                    <?php if (!empty($m['due_date'])): ?>
                        <span class="muted small"><?= e(format_date($m['due_date'], 'd M')) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($selectedActions !== []): ?>
    <div class="detail-block">
        <div class="kpi-label">Acciones</div>
        <ul class="checklist">
            <?php foreach ($selectedActions as $a): ?>
                <li class="<?= (int) $a['is_done'] ? 'is-done' : '' ?>"><?= e((string) $a['title']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="detail-actions">
    <details>
        <summary class="btn btn-ghost btn-sm">Editar objetivo</summary>
        <form method="post" action="<?= e(url('/goals/' . (int) $selected['id'])) ?>" class="stack-form" style="margin-top:12px">
            <?= csrf_field() ?>
            <label class="field"><span>Título</span><input type="text" name="title" value="<?= e((string) $selected['title']) ?>" required></label>
            <label class="field"><span>Descripción</span><textarea name="description" rows="3"><?= e((string) ($selected['description'] ?? '')) ?></textarea></label>
            <label class="field">
                <span>Estado</span>
                <select name="status">
                    <?php foreach (['idea','planned','active','paused','completed','cancelled','archived'] as $st): ?>
                        <option value="<?= $st ?>" <?= $selected['status'] === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Prioridad</span>
                <select name="priority">
                    <?php foreach (['low','medium','high','critical'] as $pr): ?>
                        <option value="<?= $pr ?>" <?= $selected['priority'] === $pr ? 'selected' : '' ?>><?= e(priority_label($pr)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Próxima acción</span><input type="text" name="next_action" value="<?= e((string) ($selected['next_action'] ?? '')) ?>"></label>
            <label class="field"><span>Vencimiento</span><input type="date" name="due_date" value="<?= e((string) ($selected['due_date'] ?? '')) ?>"></label>
            <input type="hidden" name="period_year" value="<?= e((string) ($selected['period_year'] ?? '')) ?>">
            <input type="hidden" name="area_id" value="<?= e((string) ($selected['area_id'] ?? '')) ?>">
            <input type="hidden" name="horizon" value="<?= e((string) ($selected['horizon'] ?? 'anual')) ?>">
            <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
        </form>
    </details>
    <form method="post" action="<?= e(url('/goals/' . (int) $selected['id'] . '/delete')) ?>" onsubmit="return confirm('¿Eliminar este objetivo?');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
    </form>
</div>
