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
$todayHoursLabel = format_hours_progress(
    (int) ($todayTaskStats['minutes_done'] ?? 0),
    (int) ($todayTaskStats['minutes_total'] ?? 0)
);
$weekHours = $weekHours ?? ['minutes_done' => 0, 'minutes_total' => 0];
$todayMinDone = (int) ($todayTaskStats['minutes_done'] ?? 0);
$todayMinTotal = (int) ($todayTaskStats['minutes_total'] ?? 0);
$weekMinDone = (int) ($weekHours['minutes_done'] ?? 0);
$weekMinTotal = (int) ($weekHours['minutes_total'] ?? 0);
$todayHoursPct = $todayMinTotal > 0 ? (int) min(100, round(($todayMinDone / $todayMinTotal) * 100)) : 0;
$weekHoursPct = $weekMinTotal > 0 ? (int) min(100, round(($weekMinDone / $weekMinTotal) * 100)) : 0;
$hasTodayHours = $todayMinTotal > 0;
$hasWeekHours = $weekMinTotal > 0;
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
            <div class="weekly-add-main">
                <input type="text" name="title" required maxlength="255" placeholder="Nueva tarea de hoy…" aria-label="Nueva tarea de hoy">
                <input type="time" name="start_time" aria-label="Horario de inicio" title="Horario de inicio">
                <input type="number" name="estimated_minutes" min="1" max="1440" step="1" placeholder="min" aria-label="Tiempo estimado en minutos" title="Tiempo estimado (minutos)">
                <button type="submit" class="btn btn-ghost btn-sm">+</button>
            </div>
            <?php $currentDow = (int) now_local()->format('N'); include dirname(__DIR__) . '/partials/task_repeat_picker.php'; ?>
        </form>
        <div class="weekly-day-banner" data-day-banner <?= $taskComplete ? '' : 'hidden' ?>>
            Carga diaria 100% ejecutada
        </div>
        <div class="today-hours-board">
            <div class="today-hours-head">
                <span class="today-hours-count" data-day-pct><?= $taskDone ?>/<?= $taskTotal ?></span>
                <span class="today-hours-date"><?= e($todayLabel) ?></span>
            </div>
            <div class="today-hours-grid" data-hours-grid <?= ($hasTodayHours || $hasWeekHours) ? '' : 'hidden' ?>>
                <div class="today-hours-card is-day" data-day-hours-card <?= $hasTodayHours ? '' : 'hidden' ?>>
                    <div class="today-hours-kicker">Hoy</div>
                    <div class="today-hours-value">
                        <strong data-hours-done><?= e(format_hours_from_minutes($todayMinDone)) ?></strong>
                        <span>de <span data-hours-total><?= e(format_hours_from_minutes($todayMinTotal)) ?></span> programadas</span>
                    </div>
                    <div class="progress today-hours-bar" aria-hidden="true">
                        <span data-hours-bar style="width:<?= e((string) $todayHoursPct) ?>%"></span>
                    </div>
                    <div class="today-hours-foot"><span data-hours-pct><?= (int) $todayHoursPct ?>%</span> trabajadas</div>
                    <span class="sr-only" data-day-hours><?= e($todayHoursLabel) ?></span>
                </div>
                <div class="today-hours-card is-week" data-week-hours-card <?= $hasWeekHours ? '' : 'hidden' ?>>
                    <div class="today-hours-kicker">Semana</div>
                    <div class="today-hours-value">
                        <strong data-hours-done><?= e(format_hours_from_minutes($weekMinDone)) ?></strong>
                        <span>de <span data-hours-total><?= e(format_hours_from_minutes($weekMinTotal)) ?></span> programadas</span>
                    </div>
                    <div class="progress today-hours-bar" aria-hidden="true">
                        <span data-hours-bar style="width:<?= e((string) $weekHoursPct) ?>%"></span>
                    </div>
                    <div class="today-hours-foot"><span data-hours-pct><?= (int) $weekHoursPct ?>%</span> trabajadas</div>
                    <span class="sr-only" data-week-hours-label><?= e(format_hours_progress($weekMinDone, $weekMinTotal)) ?></span>
                </div>
            </div>
        </div>
        <div class="today-panel-scroll">
            <ul class="habit-list weekly-task-list">
                <?php if ($todayTasks === []): ?>
                    <li class="empty-state">No hay tareas para hoy. <a href="<?= e(url('/weekly')) ?>">Agregar en el plan semanal</a></li>
                <?php endif; ?>
                <?php foreach ($todayTasks as $taskIndex => $task):
                    $done = (int) ($task['is_done'] ?? 0) === 1;
                    ?>
                    <li class="habit-row weekly-task-row<?= $done ? ' is-done' : '' ?>" data-task-id="<?= (int) $task['id'] ?>">
                        <span class="habit-num task-num"><?= (int) $taskIndex + 1 ?></span>
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
                        <span class="habit-name">
                            <?= e((string) $task['title']) ?>
                            <?php include dirname(__DIR__) . '/partials/task_when.php'; ?>
                            <?php include dirname(__DIR__) . '/partials/task_flags.php'; ?>
                        </span>
                        <div class="weekly-task-actions">
                            <button type="button" class="btn btn-ghost btn-sm task-expand-btn" data-task-expand aria-expanded="false" title="Imagen y pasos">▾</button>
                            <button
                                type="button"
                                class="btn btn-ghost btn-sm"
                                data-edit-task
                                data-id="<?= (int) $task['id'] ?>"
                                data-title="<?= e((string) $task['title']) ?>"
                                data-date="<?= e((string) $task['task_date']) ?>"
                                data-notes="<?= e((string) ($task['notes'] ?? '')) ?>"
                                data-start="<?= e(format_task_time($task['start_time'] ?? null)) ?>"
                                data-minutes="<?= e((string) ((int) ($task['estimated_minutes'] ?? 0) > 0 ? (int) $task['estimated_minutes'] : '')) ?>"
                            >Editar</button>
                        </div>
                        <?php $returnPath = '/today'; ?>
                        <?php include dirname(__DIR__) . '/partials/task_detail.php'; ?>
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

<script type="application/json" id="lq-week-titles"><?= json_encode($weekTitleIndex ?? [], JSON_UNESCAPED_UNICODE) ?></script>
<?php include dirname(__DIR__) . '/partials/milestone_block.php'; ?>
<dialog class="modal" id="weeklyTaskModal">
    <form method="post" id="weeklyTaskForm" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="weeklyTaskRoute" value="/weekly/0">
        <input type="hidden" name="redirect" value="/today">
        <input type="hidden" name="task_date" id="weeklyTaskDate" value="<?= e((string) $todayDate) ?>">
        <header class="modal-head">
            <h2>Editar tarea</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Título</span>
            <input type="text" name="title" id="weeklyTaskTitle" required maxlength="255">
        </label>
        <div class="form-grid-2">
            <label class="field">
                <span>Inicio</span>
                <input type="time" name="start_time" id="weeklyTaskStart">
            </label>
            <label class="field">
                <span>Tiempo estimado (minutos)</span>
                <input type="number" name="estimated_minutes" id="weeklyTaskMinutes" min="1" max="1440" step="1" placeholder="Ej. 45">
            </label>
        </div>
        <label class="field">
            <span>Notas</span>
            <textarea name="notes" id="weeklyTaskNotes" rows="2"></textarea>
        </label>
        <?php $currentDow = (int) now_local()->format('N'); include dirname(__DIR__) . '/partials/task_repeat_picker.php'; ?>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </footer>
    </form>
</dialog>
