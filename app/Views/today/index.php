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
$training = $training ?? [
    'date' => $todayDate,
    'profile' => ['has_routine' => false],
    'disciplines' => [],
    'exercises' => [],
    'latest_weight' => null,
    'bmi' => null,
    'bmi_label' => '',
    'metrics' => ['km_week' => 0, 'classes_done' => 0, 'classes_planned' => 0, 'routine_done' => 0, 'routine_total' => 0],
];
$trainDate = (string) ($training['date'] ?? $todayDate);
$trainHasRoutine = !empty($training['profile']['has_routine']);
$trainToday = [];
foreach ($training['disciplines'] ?? [] as $disc) {
    if (!empty($disc['planned_today'])) {
        $trainToday[] = $disc;
    }
}
$trainFmt = static function (float $n): string {
    return number_format($n, 1, ',', '.');
};
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
                    $isWater = ($habit['habit_key'] ?? '') === HabitService::KEY_WATER;
                    $isFruit = ($habit['habit_key'] ?? '') === HabitService::KEY_FRUIT;
                    $isQty = $isWater || $isFruit || ($habit['tracking_mode'] ?? '') === 'daily_qty';
                    $friend = $habit['suggested_friend'] ?? null;
                    $qtyDone = $isQty && ((float) ($habit['log_quantity'] ?? 0) + 0.0001 >= (float) ($habit['target_per_period'] ?? ($isFruit ? 3 : 2000)));
                    $rowDone = $isQty ? $qtyDone : $done;
                    ?>
                    <li class="habit-row<?= $rowDone ? ' is-done' : '' ?>" data-color-group="<?= $group ?>" data-habit-id="<?= (int) $habit['id'] ?>">
                        <span class="habit-num"><?= $num ?></span>
                        <span class="habit-name">
                            <?= e((string) $habit['name']) ?>
                            <?php if ($friend && ($habit['habit_key'] ?? '') === HabitService::KEY_TALK_FRIEND): ?>
                                <span class="habit-friend-hint">Hoy: <?= e((string) $friend['name']) ?></span>
                            <?php elseif (($habit['habit_key'] ?? '') === HabitService::KEY_TALK_FRIEND): ?>
                                <span class="habit-friend-hint muted"><a href="<?= e(url('/friends')) ?>">Cargá amigos/as</a></span>
                            <?php endif; ?>
                        </span>
                        <?php if ($isFruit):
                            $returnPath = '/today';
                            include dirname(__DIR__) . '/partials/habit_fruit.php';
                        elseif ($isWater || $isQty):
                            $returnPath = '/today';
                            include dirname(__DIR__) . '/partials/habit_water.php';
                        else: ?>
                        <form method="post" action="<?= e(url('/habits/log')) ?>" class="habit-toggle-form" data-habit-toggle>
                            <?= csrf_field() ?>
                            <input type="hidden" name="habit_id" value="<?= (int) $habit['id'] ?>">
                            <input type="hidden" name="date" value="<?= e($todayDate) ?>">
                            <input type="hidden" name="status" value="<?= $done ? 'missed' : 'completed' ?>">
                            <input type="hidden" name="redirect" value="/today">
                            <?php if ($friend): ?>
                                <input type="hidden" name="friend_id" value="<?= (int) $friend['id'] ?>">
                            <?php endif; ?>
                            <label class="check-toggle">
                                <input type="checkbox" <?= $done ? 'checked' : '' ?> aria-label="Marcar <?= e((string) $habit['name']) ?>">
                                <span></span>
                            </label>
                        </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </article>

    <article class="card weekly-today-card today-panel today-panel--scroll<?= $taskComplete ? ' is-complete' : '' ?>" data-day-date="<?= e($todayDate) ?>">
        <div class="card-header">
            <h2>Tareas del día</h2>
            <div class="btn-row">
                <button type="button" class="btn btn-primary btn-sm" data-new-task data-date="<?= e($todayDate) ?>">+ Nueva tarea</button>
                <a class="text-link" href="<?= e(url('/weekly')) ?>">Plan semanal →</a>
            </div>
        </div>
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
            <?php
            $tasks = $todayTasks;
            $returnPath = '/today';
            $showDelete = false;
            $dayFilter = '';
            include dirname(__DIR__) . '/partials/task_day_list.php';
            ?>
        </div>
    </article>

    <article class="card today-training">
        <div class="card-header">
            <h2>Entrenamiento de hoy</h2>
            <a class="text-link" href="<?= e(url('/deportes?date=' . $trainDate)) ?>">Ver todo →</a>
        </div>
        <p class="today-training-meta muted small">
            <span>Peso <strong><?= $training['latest_weight'] !== null ? e($trainFmt((float) $training['latest_weight'])) . ' kg' : '—' ?></strong></span>
            <span>BMI <strong><?= $training['bmi'] !== null ? e($trainFmt((float) $training['bmi'])) . ' ' . e((string) $training['bmi_label']) : '—' ?></strong></span>
            <span>Km semana <strong><?= e($trainFmt((float) ($training['metrics']['km_week'] ?? 0))) ?></strong></span>
            <span>Clases <strong><?= (int) ($training['metrics']['classes_done'] ?? 0) ?>/<?= (int) ($training['metrics']['classes_planned'] ?? 0) ?></strong></span>
        </p>
        <?php if ($trainToday === [] && !$trainHasRoutine): ?>
            <p class="empty-state">Hoy no hay clase ni kilómetros pautados. Las disciplinas se arman en Entrenamiento y no entran en Hábitos.</p>
        <?php else: ?>
            <div class="today-training-grid">
                <div>
                    <h3>Hoy</h3>
                    <?php if ($trainToday === []): ?>
                        <p class="muted small">Sin clase ni kilómetros para este día.</p>
                    <?php else: ?>
                        <ul class="sport-disciplines">
                            <?php foreach ($trainToday as $disc): ?>
                                <li class="sport-discipline">
                                    <div class="sport-discipline-copy">
                                        <strong><?= e((string) $disc['name']) ?></strong>
                                        <span class="muted small"><?= $disc['kind'] === 'distance' ? 'Kilómetros' : 'Clase' ?></span>
                                    </div>
                                    <div class="sport-discipline-action">
                                        <?php if ($disc['kind'] === 'distance'): ?>
                                            <form method="post" action="<?= e(form_action('/deportes/' . $disc['id'] . '/log')) ?>" class="sport-km-form">
                                                <?= csrf_field() ?>
                                                <?= route_field('/deportes/' . $disc['id'] . '/log') ?>
                                                <input type="hidden" name="date" value="<?= e($trainDate) ?>">
                                                <input type="hidden" name="redirect" value="/today">
                                                <label>
                                                    <span class="sr-only">Kilómetros de <?= e((string) $disc['name']) ?></span>
                                                    <input type="number" name="quantity" min="0" max="300" step="0.1" inputmode="decimal" value="<?= $disc['quantity'] !== null ? e((string) $disc['quantity']) : '' ?>" placeholder="0">
                                                </label>
                                                <button type="submit" class="btn btn-ghost btn-sm">km</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="<?= e(form_action('/deportes/' . $disc['id'] . '/log')) ?>">
                                                <?= csrf_field() ?>
                                                <?= route_field('/deportes/' . $disc['id'] . '/log') ?>
                                                <input type="hidden" name="date" value="<?= e($trainDate) ?>">
                                                <input type="hidden" name="redirect" value="/today">
                                                <input type="hidden" name="attended" value="<?= !empty($disc['attended']) ? '0' : '1' ?>">
                                                <button type="submit" class="btn <?= !empty($disc['attended']) ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= !empty($disc['attended']) ? 'Fui' : 'Marcar que fui' ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div>
                    <h3>Rutina</h3>
                    <?php if (!$trainHasRoutine): ?>
                        <p class="muted small">Sin rutina personal. Si la activás, se tacha acá y no cuenta en Hábitos.</p>
                    <?php elseif (($training['exercises'] ?? []) === []): ?>
                        <p class="muted small">Todavía no hay ejercicios. Sumalos en Entrenamiento.</p>
                    <?php else: ?>
                        <ul class="habit-list">
                            <?php foreach ($training['exercises'] as $index => $exercise): ?>
                                <li class="habit-row<?= !empty($exercise['done']) ? ' is-done' : '' ?>" data-color-group="<?= habit_color_group($index + 1) ?>">
                                    <span class="habit-num"><?= $index + 1 ?></span>
                                    <span class="habit-meta">
                                        <span class="habit-name"><?= e((string) $exercise['name']) ?></span>
                                        <span class="muted small">×<?= (int) ($exercise['reps'] ?? 10) ?></span>
                                    </span>
                                    <form method="post" action="<?= e(form_action('/deportes/exercises/' . $exercise['id'] . '/toggle')) ?>" class="habit-toggle-form">
                                        <?= csrf_field() ?>
                                        <?= route_field('/deportes/exercises/' . $exercise['id'] . '/toggle') ?>
                                        <input type="hidden" name="date" value="<?= e($trainDate) ?>">
                                        <input type="hidden" name="redirect" value="/today">
                                        <label class="check-toggle" title="Marcar hecho">
                                            <input type="checkbox" <?= !empty($exercise['done']) ? 'checked' : '' ?> onchange="this.form.submit()" aria-label="<?= e((string) $exercise['name']) ?>">
                                            <span></span>
                                        </label>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
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
        <input type="hidden" name="r" id="weeklyTaskRoute" value="/weekly">
        <input type="hidden" name="redirect" value="/today">
        <input type="hidden" name="task_date" id="weeklyTaskDate" value="<?= e((string) $todayDate) ?>">
        <header class="modal-head">
            <h2 id="weeklyTaskModalTitle">Nueva tarea</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Título</span>
            <input type="text" name="title" id="weeklyTaskTitle" required maxlength="255">
        </label>
        <?php $selectedKind = 'work'; include dirname(__DIR__) . '/partials/task_kind_field.php'; ?>
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
            <button type="submit" class="btn btn-primary" id="weeklyTaskSubmit">Crear</button>
        </footer>
    </form>
</dialog>
