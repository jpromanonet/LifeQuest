<?php
/** @var list<array> $friends */
/** @var array{id:int,name:string}|null $suggested */
/** @var array{total:int,talked_week:int,talks_week:int,talked_month:int,never:int,last_talk_date:?string} $stats */
$stats = $stats ?? [
    'total' => count($friends),
    'talked_week' => 0,
    'talks_week' => 0,
    'talked_month' => 0,
    'never' => 0,
    'last_talk_date' => null,
];
$total = (int) ($stats['total'] ?? count($friends));
?>
<section class="page-header with-actions">
    <div>
        <h1>Amigos/as</h1>
        <p class="muted">Cercanos · el hábito de hablar te sugiere uno sin repetir</p>
    </div>
</section>

<section class="kpi-grid kpi-grid-4" style="margin-bottom:14px">
    <article class="card kpi-card kpi-card--lavender">
        <div class="kpi-label">En la lista</div>
        <div class="kpi-value"><?= $total ?></div>
        <div class="muted small"><?= $total === 1 ? 'amigo/a' : 'amigos/as' ?></div>
    </article>
    <article class="card kpi-card kpi-card--mint">
        <div class="kpi-label">Esta semana</div>
        <div class="kpi-value"><?= (int) ($stats['talked_week'] ?? 0) ?></div>
        <div class="muted small"><?= (int) ($stats['talks_week'] ?? 0) ?> charlas · lun–dom</div>
    </article>
    <article class="card kpi-card kpi-card--peach">
        <div class="kpi-label">Este mes</div>
        <div class="kpi-value"><?= (int) ($stats['talked_month'] ?? 0) ?></div>
        <div class="muted small">personas distintas</div>
    </article>
    <article class="card kpi-card kpi-card--yellow">
        <div class="kpi-label">Sin hablar aún</div>
        <div class="kpi-value"><?= (int) ($stats['never'] ?? 0) ?></div>
        <div class="muted small">
            <?php if (!empty($stats['last_talk_date'])): ?>
                última: <?= e(format_date((string) $stats['last_talk_date'], 'd M')) ?>
            <?php else: ?>
                todavía no hay registros
            <?php endif; ?>
        </div>
    </article>
</section>

<?php if ($suggested): ?>
    <article class="card kpi-card kpi-card--mint" style="margin-bottom:14px">
        <div class="kpi-label">Hoy te toca hablar con</div>
        <div class="kpi-value" style="font-size:1.45rem"><?= e($suggested['name']) ?></div>
        <div class="muted small">Se confirma al tildar el hábito en Hoy · queda fijo hasta mañana</div>
    </article>
<?php endif; ?>

<section class="card">
    <form method="post" action="<?= e(form_action()) ?>" class="weekly-add-form" data-lq-save>
        <?= csrf_field() ?>
        <?= route_field('/friends') ?>
        <div class="weekly-add-main">
            <input type="text" name="name" required maxlength="160" placeholder="Nombre…" aria-label="Nombre del amigo o amiga">
            <button type="submit" class="btn btn-primary btn-sm">Agregar</button>
        </div>
    </form>

    <?php if ($friends === []): ?>
        <p class="empty-state muted">Todavía no hay nadie. Agregá a quien sea cercano.</p>
    <?php else: ?>
        <ul class="milestone-list" style="margin-top:12px">
            <?php foreach ($friends as $friend): ?>
                <li class="milestone-row">
                    <form method="post" action="<?= e(form_action()) ?>" class="friend-edit-form" data-lq-save>
                        <?= csrf_field() ?>
                        <?= route_field('/friends/' . (int) $friend['id']) ?>
                        <input type="text" name="name" required maxlength="160" value="<?= e((string) $friend['name']) ?>" aria-label="Nombre">
                        <button type="submit" class="btn btn-ghost btn-sm">Guardar</button>
                    </form>
                    <form method="post" action="<?= e(form_action()) ?>" data-lq-save onsubmit="return confirm('¿Quitar a <?= e((string) $friend['name']) ?>?');">
                        <?= csrf_field() ?>
                        <?= route_field('/friends/' . (int) $friend['id'] . '/delete') ?>
                        <button type="submit" class="btn btn-danger btn-sm">×</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
