<?php
/** @var string $greeting */
/** @var string $firstName */
/** @var string $todayLabel */
/** @var list<array<string,mixed>> $todayHabits */
/** @var array $overview */
/** @var int $habitsDone */
/** @var int $habitsTotal */
/** @var array $weeklyChart */
/** @var array $yearProgress */
/** @var array $yearRingChart */
/** @var array $yearMonthsChart */
/** @var string $todayDate */
/** @var list<array> $todayTasks */
/** @var array $todayTaskStats */

$weekValues = $overview['weekly_progress'] ?? [];
$weekAvg = $weekValues !== [] ? round(array_sum($weekValues) / count($weekValues)) : 0;
$weekPct = $overview['weekly_progress'][(int) now_local()->format('N') - 1] ?? $weekAvg;
$yp = $yearProgress ?? [
    'year' => (int) now_local()->format('Y'),
    'percent' => 0,
    'days_elapsed' => 0,
    'days_remaining' => 0,
    'days_total' => 365,
    'weeks_elapsed' => 0,
    'weeks_remaining' => 0,
    'weeks_total' => 52,
];
$taskTotal = (int) ($todayTaskStats['total'] ?? count($todayTasks));
$taskDone = (int) ($todayTaskStats['done'] ?? 0);
$taskPct = (float) ($todayTaskStats['percent'] ?? 0);
$taskComplete = !empty($todayTaskStats['complete']);
?>
<section class="page-header">
    <div>
        <h1><?= e($greeting) ?>, <?= e($firstName) ?></h1>
        <p class="muted"><?= e($todayLabel) ?>.</p>
    </div>
</section>

<section class="kpi-grid">
    <article class="card kpi-card kpi-card--primary">
        <div class="kpi-label">Progreso semanal</div>
        <div class="kpi-value"><?= (int) $weekAvg ?>%</div>
        <div class="progress"><span style="width:<?= e((string) min(100, (float) $weekPct)) ?>%"></span></div>
    </article>
    <article class="card kpi-card kpi-card--yellow">
        <div class="kpi-label">Racha actual</div>
        <div class="kpi-value"><?= (int) $overview['current_streak_best'] ?> <span class="kpi-unit">días</span></div>
    </article>
    <article class="card kpi-card kpi-card--lavender">
        <div class="kpi-label">Objetivos activos</div>
        <div class="kpi-value"><?= (int) $overview['goals_active'] ?></div>
    </article>
    <article class="card kpi-card kpi-card--mint">
        <div class="kpi-label">Hábitos de hoy</div>
        <div class="kpi-value"><?= (int) $habitsDone ?>/<?= (int) $habitsTotal ?></div>
        <div class="progress"><span style="width:<?= $habitsTotal > 0 ? e((string) round(($habitsDone / $habitsTotal) * 100)) : '0' ?>%"></span></div>
    </article>
</section>

<section class="today-layout">
    <article class="card today-panel today-panel--scroll">
        <div class="card-header">
            <h2>Hábitos de hoy</h2>
            <a class="text-link" href="<?= e(url('/habits')) ?>">Ver todos →</a>
        </div>
        <div class="today-panel-scroll">
            <ul class="habit-list">
                <?php if ($todayHabits === []): ?>
                    <li class="empty-state">No hay hábitos programados para hoy.</li>
                <?php endif; ?>
                <?php foreach ($todayHabits as $habit):
                    $done = ($habit['log_status'] ?? null) === 'completed' || ($habit['log_status'] ?? null) === 'partial';
                    $num = (int) ($habit['display_number'] ?? 0);
                    $group = (int) ($habit['color_group'] ?? habit_color_group($num));
                    ?>
                    <li class="habit-row<?= $done ? ' is-done' : '' ?>" data-color-group="<?= $group ?>" data-habit-id="<?= (int) $habit['id'] ?>">
                        <span class="habit-num"><?= $num ?></span>
                        <span class="habit-name"><?= e((string) $habit['name']) ?></span>
                        <form method="post" action="<?= e(url('/habits/log')) ?>" class="habit-toggle-form" data-habit-toggle>
                            <?= csrf_field() ?>
                            <input type="hidden" name="habit_id" value="<?= (int) $habit['id'] ?>">
                            <input type="hidden" name="date" value="<?= e($todayDate) ?>">
                            <input type="hidden" name="status" value="<?= $done ? 'missed' : 'completed' ?>">
                            <input type="hidden" name="redirect" value="/today">
                            <label class="check-toggle">
                                <input type="checkbox" <?= $done ? 'checked' : '' ?> aria-label="Marcar <?= e((string) $habit['name']) ?>">
                                <span></span>
                            </label>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </article>

    <article class="card weekly-today-card today-panel today-panel--scroll<?= $taskComplete ? ' is-complete' : '' ?>" data-day-date="<?= e($todayDate) ?>">
        <div class="card-header">
            <h2>Tareas del día</h2>
            <a class="text-link" href="<?= e(url('/weekly')) ?>">Plan semanal →</a>
        </div>
        <form method="post" action="<?= e(form_action()) ?>" class="weekly-add-form today-add-form" data-lq-save>
            <?= csrf_field() ?>
            <?= route_field('/weekly') ?>
            <input type="hidden" name="task_date" value="<?= e($todayDate) ?>">
            <input type="hidden" name="redirect" value="/today">
            <input type="text" name="title" required maxlength="255" placeholder="Nueva tarea de hoy…" aria-label="Nueva tarea de hoy">
            <button type="submit" class="btn btn-ghost btn-sm">+</button>
        </form>
        <div class="weekly-day-banner" data-day-banner <?= $taskComplete ? '' : 'hidden' ?>>
            Carga diaria 100% ejecutada
        </div>
        <div class="muted small today-task-meta">
            <span data-day-pct><?= $taskDone ?>/<?= $taskTotal ?></span> · <?= e($todayLabel) ?>
        </div>
        <div class="today-panel-scroll">
            <ul class="habit-list weekly-task-list">
                <?php if ($todayTasks === []): ?>
                    <li class="empty-state">No hay tareas para hoy. <a href="<?= e(url('/weekly')) ?>">Agregar en el plan semanal</a></li>
                <?php endif; ?>
                <?php foreach ($todayTasks as $task):
                    $done = (int) ($task['is_done'] ?? 0) === 1;
                    ?>
                    <li class="habit-row weekly-task-row<?= $done ? ' is-done' : '' ?>" data-task-id="<?= (int) $task['id'] ?>">
                        <form method="post" action="<?= e(form_action()) ?>" class="habit-toggle-form" data-task-toggle>
                            <?= csrf_field() ?>
                            <?= route_field('/weekly/' . (int) $task['id'] . '/toggle') ?>
                            <input type="hidden" name="status" value="<?= $done ? 'pending' : 'completed' ?>">
                            <input type="hidden" name="redirect" value="/today">
                            <label class="check-toggle">
                                <input type="checkbox" <?= $done ? 'checked' : '' ?> aria-label="Marcar <?= e((string) $task['title']) ?>">
                                <span></span>
                            </label>
                        </form>
                        <span class="habit-name"><?= e((string) $task['title']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </article>

    <article class="card year-progress-card today-panel today-panel--bottom">
        <div class="card-header">
            <h2>Progreso del año</h2>
            <span class="muted small"><?= (int) $yp['year'] ?></span>
        </div>
        <div class="year-progress">
            <div class="year-ring-wrap">
                <div class="chart-box chart-box--ring">
                    <canvas id="yearRing" aria-label="Porcentaje del año transcurrido"></canvas>
                </div>
                <div class="year-ring-center">
                    <strong><?= e(number_format((float) $yp['percent'], 0)) ?>%</strong>
                    <span>del año</span>
                </div>
            </div>
            <div class="year-progress-stats">
                <div class="year-stat">
                    <span class="year-stat-value"><?= (int) $yp['days_elapsed'] ?></span>
                    <span class="year-stat-label">días pasados</span>
                </div>
                <div class="year-stat">
                    <span class="year-stat-value"><?= (int) $yp['days_remaining'] ?></span>
                    <span class="year-stat-label">días restantes</span>
                </div>
                <div class="year-stat">
                    <span class="year-stat-value"><?= (int) $yp['weeks_elapsed'] ?></span>
                    <span class="year-stat-label">semanas pasadas</span>
                </div>
                <div class="year-stat">
                    <span class="year-stat-value"><?= (int) $yp['weeks_remaining'] ?></span>
                    <span class="year-stat-label">semanas restantes</span>
                </div>
            </div>
        </div>
        <div class="year-progress-bar-block">
            <div class="month-progress-head">
                <span class="muted small">Avance de <?= (int) $yp['year'] ?></span>
                <strong><?= (int) $yp['days_elapsed'] ?> / <?= (int) $yp['days_total'] ?> días</strong>
            </div>
            <div class="year-bar-track">
                <div class="progress year-bar"><span style="width:<?= e((string) min(100, (float) $yp['percent'])) ?>%"></span></div>
                <span
                    class="year-bar-now"
                    style="--now-pct:<?= e(number_format(min(100, max(0, (float) $yp['percent'])), 2, '.', '')) ?>%"
                    title="Hoy · <?= e(number_format((float) $yp['percent'], 0)) ?>% del año"
                    aria-hidden="true"
                ></span>
            </div>
        </div>
        <div class="year-months-block">
            <div class="month-progress-head">
                <span class="muted small">Objetivos por mes</span>
                <span class="muted small">% con el mes tildado</span>
            </div>
            <div class="chart-box chart-box--months">
                <canvas id="yearMonths" aria-label="Avance de objetivos por mes"></canvas>
            </div>
        </div>
        <script type="application/json" data-chart="yearRing"><?= json_encode($yearRingChart ?? [], JSON_UNESCAPED_UNICODE) ?></script>
        <script type="application/json" data-chart="yearMonths"><?= json_encode($yearMonthsChart ?? [], JSON_UNESCAPED_UNICODE) ?></script>
    </article>

    <article class="card today-panel today-panel--bottom">
        <div class="card-header">
            <h2>Tu semana de hábitos</h2>
            <span class="muted small">Por hábito · colores del día</span>
        </div>
        <div class="chart-box chart-box--week">
            <canvas id="weekChart" aria-label="Progreso semanal por hábito"></canvas>
        </div>
        <script type="application/json" data-chart="weekChart"><?= json_encode([
            'type' => 'bar',
            'data' => $weeklyChart,
            'options' => [
                'maintainAspectRatio' => false,
                'scales' => [
                    'x' => [
                        'stacked' => true,
                        'grid' => ['display' => false],
                    ],
                    'y' => [
                        'stacked' => true,
                        'beginAtZero' => true,
                        'max' => 100,
                    ],
                ],
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'bottom',
                        'labels' => [
                            'boxWidth' => 10,
                            'boxHeight' => 10,
                            'padding' => 8,
                            'font' => ['size' => 11],
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE) ?></script>
    </article>
</section>
