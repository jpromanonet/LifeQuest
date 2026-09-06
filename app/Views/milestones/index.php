<?php
/** @var list<array> $upcoming */
/** @var list<array> $due */
/** @var list<array> $done */
/** @var array{upcoming:int,pending_due:int,done:int,total:int} $stats */
/** @var string $todayDate */

$formatDate = static function (string $date): string {
    try {
        $dt = new DateTimeImmutable($date, now_local()->getTimezone());
        $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $months = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
        return $days[(int) $dt->format('w')] . ' ' . $dt->format('j') . ' ' . $months[(int) $dt->format('n')] . ' ' . $dt->format('Y');
    } catch (Throwable) {
        return $date;
    }
};
?>
<section class="page-header with-actions">
    <div>
        <h1>Hitos</h1>
        <p class="muted">Fechas señaladas · ese día bloquean Hoy hasta marcarlas hechas</p>
    </div>
    <button type="button" class="btn btn-primary" data-open-modal="milestoneModal">+ Nuevo hito</button>
</section>

<section class="kpi-grid kpi-grid-3">
    <article class="card kpi-card kpi-card--mint">
        <div class="kpi-label">Pendientes hoy</div>
        <div class="kpi-value"><?= (int) $stats['pending_due'] ?></div>
        <div class="muted small">Bloquean la home</div>
    </article>
    <article class="card kpi-card kpi-card--primary">
        <div class="kpi-label">Próximos</div>
        <div class="kpi-value"><?= (int) $stats['upcoming'] ?></div>
        <div class="muted small">Aún no llegaron</div>
    </article>
    <article class="card kpi-card kpi-card--lavender">
        <div class="kpi-label">Hechos</div>
        <div class="kpi-value"><?= (int) $stats['done'] ?></div>
        <div class="muted small">De <?= (int) $stats['total'] ?> en total</div>
    </article>
</section>

<?php
$sections = [
    ['key' => 'due', 'title' => 'Para hoy o vencidos', 'rows' => $due, 'empty' => 'Ningún hito pendiente de marcar.'],
    ['key' => 'upcoming', 'title' => 'Próximos', 'rows' => $upcoming, 'empty' => 'No hay hitos futuros.'],
    ['key' => 'done', 'title' => 'Hechos', 'rows' => $done, 'empty' => 'Todavía no marcaste ninguno.'],
];
foreach ($sections as $section):
    $rows = $section['rows'];
    ?>
    <section class="card milestone-section">
        <header class="section-head">
            <h2><?= e($section['title']) ?></h2>
            <span class="muted small"><?= count($rows) ?></span>
        </header>
        <?php if ($rows === []): ?>
            <p class="empty-state muted small"><?= e($section['empty']) ?></p>
        <?php else: ?>
            <ul class="milestone-list">
                <?php foreach ($rows as $row):
                    $isDone = (int) ($row['is_done'] ?? 0) === 1;
                    $date = (string) $row['milestone_date'];
                    $isToday = $date === $todayDate;
                    ?>
                    <li class="milestone-row<?= $isDone ? ' is-done' : '' ?><?= $isToday && !$isDone ? ' is-today' : '' ?>">
                        <div class="milestone-main">
                            <strong class="milestone-title"><?= e((string) $row['title']) ?></strong>
                            <span class="milestone-date"><?= e($formatDate($date)) ?><?= $isToday ? ' · Hoy' : '' ?></span>
                            <?php if (!empty($row['notes'])): ?>
                                <p class="milestone-notes muted small"><?= e((string) $row['notes']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="milestone-actions">
                            <?php if (!$isDone): ?>
                                <form method="post" action="<?= e(form_action()) ?>" data-lq-save>
                                    <?= csrf_field() ?>
                                    <?= route_field('/milestones/' . (int) $row['id'] . '/complete') ?>
                                    <input type="hidden" name="redirect" value="/milestones">
                                    <button type="submit" class="btn btn-primary btn-sm">Hecho</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(form_action()) ?>" data-lq-save>
                                    <?= csrf_field() ?>
                                    <?= route_field('/milestones/' . (int) $row['id'] . '/reopen') ?>
                                    <button type="submit" class="btn btn-ghost btn-sm">Reabrir</button>
                                </form>
                            <?php endif; ?>
                            <button
                                type="button"
                                class="btn btn-ghost btn-sm"
                                data-edit-milestone
                                data-id="<?= (int) $row['id'] ?>"
                                data-title="<?= e((string) $row['title']) ?>"
                                data-date="<?= e($date) ?>"
                                data-notes="<?= e((string) ($row['notes'] ?? '')) ?>"
                            >Editar</button>
                            <form method="post" action="<?= e(form_action()) ?>" data-lq-save onsubmit="return confirm('¿Eliminar este hito?');">
                                <?= csrf_field() ?>
                                <?= route_field('/milestones/' . (int) $row['id'] . '/delete') ?>
                                <button type="submit" class="btn btn-danger btn-sm">×</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<dialog class="modal" id="milestoneModal">
    <form method="post" id="milestoneForm" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="milestoneRoute" value="/milestones">
        <header class="modal-head">
            <h2 id="milestoneModalTitle">Nuevo hito</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Título</span>
            <input type="text" name="title" id="milestoneTitle" required maxlength="255" placeholder="Ej. Cumpleaños de…">
        </label>
        <label class="field">
            <span>Fecha</span>
            <input type="date" name="milestone_date" id="milestoneDate" required value="<?= e($todayDate) ?>">
        </label>
        <label class="field">
            <span>Notas (opcional)</span>
            <textarea name="notes" id="milestoneNotes" rows="2" placeholder="Detalle breve…"></textarea>
        </label>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </footer>
    </form>
</dialog>
