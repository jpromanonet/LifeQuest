<?php
/** @var list<array> $board */
/** @var list<array> $visibleDays */
/** @var array $stats */
/** @var DateTimeImmutable $monday */
/** @var DateTimeImmutable $sunday */
/** @var string $prevMonday */
/** @var string $nextMonday */
/** @var string $weekParam */
/** @var string $dayFilter */
/** @var string $todayDate */
/** @var bool $isCurrentWeek */

$weekRange = $monday->format('d/m') . ' – ' . $sunday->format('d/m/Y');
$visibleDays = $visibleDays ?? $board;
$dayFilter = $dayFilter ?? 'all';
$weekParam = $weekParam ?? $monday->format('Y-m-d');
$todayDate = $todayDate ?? now_local()->format('Y-m-d');

$weekUrl = static function (string $week, string $day = 'all') use ($weekParam): string {
    $params = ['week' => $week];
    if ($day !== '' && $day !== 'all') {
        $params['day'] = $day;
    } elseif ($day === 'all') {
        $params['day'] = 'all';
    }
    return url('/weekly?' . http_build_query($params));
};
?>
<section class="page-header with-actions">
    <div>
        <h1>Plan semanal</h1>
        <p class="muted">Tareas de lunes a domingo · <?= e($weekRange) ?></p>
    </div>
    <div class="btn-row year-nav-week">
        <a class="btn btn-ghost btn-sm" href="<?= e($weekUrl($prevMonday, $dayFilter === 'all' ? 'all' : $dayFilter)) ?>">← Semana anterior</a>
        <?php if (!$isCurrentWeek): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/weekly')) ?>">Semana actual</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= e($weekUrl($nextMonday, $dayFilter === 'all' ? 'all' : $dayFilter)) ?>">Semana siguiente →</a>
    </div>
</section>

<section class="kpi-grid kpi-grid-4 weekly-kpi">
    <article class="card kpi-card kpi-card--mint">
        <div class="kpi-label">Cumplimiento semanal</div>
        <div class="kpi-value"><?= e(number_format((float) $stats['week_percent'], 0)) ?>%</div>
        <div class="progress kpi-bar"><span style="width:<?= e((string) min(100, (float) $stats['week_percent'])) ?>%"></span></div>
        <div class="muted small"><?= (int) $stats['week_done'] ?> / <?= (int) $stats['week_total'] ?> tareas</div>
    </article>
    <article class="card kpi-card kpi-card--primary">
        <div class="kpi-label">Días con carga 100%</div>
        <div class="kpi-value"><?= (int) $stats['days_complete'] ?>/<?= max(1, (int) $stats['days_planned']) ?></div>
        <div class="muted small">Días con todas las tareas hechas</div>
    </article>
    <article class="card kpi-card kpi-card--lavender">
        <div class="kpi-label">Hoy</div>
        <div class="kpi-value"><?= (int) $stats['today_done'] ?>/<?= (int) $stats['today_total'] ?></div>
        <div class="progress kpi-bar"><span style="width:<?= e((string) min(100, (float) $stats['today_percent'])) ?>%"></span></div>
        <div class="muted small"><?= e(number_format((float) $stats['today_percent'], 0)) ?>% del día</div>
    </article>
    <article class="card kpi-card kpi-card--peach">
        <div class="kpi-label">Días planificados</div>
        <div class="kpi-value"><?= (int) $stats['days_planned'] ?></div>
        <div class="muted small">De 7 en esta semana</div>
    </article>
</section>

<nav class="weekly-day-filter" aria-label="Filtrar por día">
    <a
        class="weekly-day-chip<?= $dayFilter === 'all' ? ' is-active' : '' ?>"
        href="<?= e($weekUrl($weekParam, 'all')) ?>"
    >Toda la semana</a>
    <?php foreach ($board as $day):
        $date = (string) $day['date'];
        $isActive = $dayFilter === $date;
        $isToday = !empty($day['is_today']);
        ?>
        <a
            class="weekly-day-chip<?= $isActive ? ' is-active' : '' ?><?= $isToday ? ' is-today-chip' : '' ?>"
            href="<?= e($weekUrl($weekParam, $date)) ?>"
        >
            <?= e((string) $day['label']) ?>
            <?php if ($isToday): ?><span>Hoy</span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<section class="weekly-board weekly-board--stack">
    <?php foreach ($visibleDays as $day):
        $returnPath = '/weekly?' . http_build_query([
            'week' => $weekParam,
            'day' => $dayFilter,
        ]);
        ?>
        <article
            class="card weekly-day<?= !empty($day['is_today']) ? ' is-today' : '' ?><?= !empty($day['complete']) ? ' is-complete' : '' ?>"
            data-day-date="<?= e((string) $day['date']) ?>"
            <?= !empty($day['is_today']) ? 'id="weekly-day-today"' : '' ?>
        >
            <header class="weekly-day-head">
                <div>
                    <strong><?= e((string) $day['label']) ?> <?= e((string) $day['date_label']) ?></strong>
                    <?php if (!empty($day['is_today'])): ?>
                        <span class="weekly-today-pill">Hoy</span>
                    <?php endif; ?>
                </div>
                <span class="muted small weekly-day-pct" data-day-pct><?= (int) $day['done'] ?>/<?= (int) $day['total'] ?></span>
            </header>

            <form method="post" action="<?= e(form_action()) ?>" class="weekly-add-form" data-lq-save>
                <?= csrf_field() ?>
                <?= route_field('/weekly') ?>
                <input type="hidden" name="task_date" value="<?= e((string) $day['date']) ?>">
                <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
                <input type="hidden" name="filter_day" value="<?= e($dayFilter) ?>">
                <input type="text" name="title" required maxlength="255" placeholder="Nueva tarea…" aria-label="Nueva tarea para <?= e((string) $day['label']) ?>">
                <button type="submit" class="btn btn-ghost btn-sm">+</button>
            </form>

            <div class="weekly-day-banner" data-day-banner <?= empty($day['complete']) ? 'hidden' : '' ?>>
                Carga diaria 100% ejecutada
            </div>

            <ul class="habit-list weekly-task-list">
                <?php if ($day['tasks'] === []): ?>
                    <li class="empty-state muted small">Sin tareas</li>
                <?php endif; ?>
                <?php foreach ($day['tasks'] as $task):
                    $done = (int) ($task['is_done'] ?? 0) === 1;
                    ?>
                    <li class="habit-row weekly-task-row<?= $done ? ' is-done' : '' ?>" data-task-id="<?= (int) $task['id'] ?>">
                        <form method="post" action="<?= e(form_action()) ?>" class="habit-toggle-form" data-task-toggle>
                            <?= csrf_field() ?>
                            <?= route_field('/weekly/' . (int) $task['id'] . '/toggle') ?>
                            <input type="hidden" name="status" value="<?= $done ? 'pending' : 'completed' ?>">
                            <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
                            <label class="check-toggle">
                                <input type="checkbox" <?= $done ? 'checked' : '' ?> aria-label="Marcar <?= e((string) $task['title']) ?>">
                                <span></span>
                            </label>
                        </form>
                        <span class="habit-name"><?= e((string) $task['title']) ?></span>
                        <div class="weekly-task-actions">
                            <button
                                type="button"
                                class="btn btn-ghost btn-sm"
                                data-edit-task
                                data-id="<?= (int) $task['id'] ?>"
                                data-title="<?= e((string) $task['title']) ?>"
                                data-date="<?= e((string) $task['task_date']) ?>"
                                data-notes="<?= e((string) ($task['notes'] ?? '')) ?>"
                            >Editar</button>
                            <form method="post" action="<?= e(form_action()) ?>" data-lq-save onsubmit="return confirm('¿Eliminar esta tarea?');">
                                <?= csrf_field() ?>
                                <?= route_field('/weekly/' . (int) $task['id'] . '/delete') ?>
                                <input type="hidden" name="filter_day" value="<?= e($dayFilter) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">×</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </article>
    <?php endforeach; ?>
</section>

<dialog class="modal" id="weeklyTaskModal">
    <form method="post" id="weeklyTaskForm" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="weeklyTaskRoute" value="/weekly/0">
        <input type="hidden" name="filter_day" value="<?= e($dayFilter) ?>">
        <header class="modal-head">
            <h2 id="weeklyTaskModalTitle">Editar tarea</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Título</span>
            <input type="text" name="title" id="weeklyTaskTitle" required maxlength="255">
        </label>
        <label class="field">
            <span>Día</span>
            <select name="task_date" id="weeklyTaskDate">
                <?php foreach ($board as $day): ?>
                    <option value="<?= e((string) $day['date']) ?>"><?= e($day['label'] . ' ' . $day['date_label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Notas</span>
            <textarea name="notes" id="weeklyTaskNotes" rows="2"></textarea>
        </label>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </footer>
    </form>
</dialog>
<script>
(function () {
  var today = document.getElementById('weekly-day-today');
  if (today && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    window.setTimeout(function () {
      today.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }, 80);
  }
})();
</script>
