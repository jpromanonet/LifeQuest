<?php
/** @var array<string, mixed> $board */
/** @var array<int, string> $dayShort */

$date = (string) $board['date'];
$profile = $board['profile'];
$metrics = $board['metrics'];
$week = $board['week'];
$height = $profile['height_cm'];
$hasRoutine = !empty($profile['has_routine']);
$back = '/deportes?date=' . $date;
$repsAtGoal = 0;
$repsSum = 0;
foreach ($board['exercises'] as $exercise) {
    $reps = (int) ($exercise['reps'] ?? 10);
    $repsSum += $reps;
    if ($reps >= SportService::REPS_GOAL) {
        $repsAtGoal++;
    }
}
$repsTotal = count($board['exercises']);
$repsPct = $repsTotal > 0 ? (int) round(($repsSum / ($repsTotal * SportService::REPS_GOAL)) * 100) : 0;

$fmt = static function (float $n, int $decimals = 1): string {
    return number_format($n, $decimals, ',', '.');
};

$sportToday = now_local()->format('Y-m-d');
$sportNow = now_local();
$sportSelected = new DateTimeImmutable($date);
$sportMonday = $sportSelected->modify('-' . ((int) $sportSelected->format('N') - 1) . ' days');
$sportCurrentMonday = $sportNow->modify('-' . ((int) $sportNow->format('N') - 1) . ' days')->format('Y-m-d');
$isCurrentSportWeek = $sportMonday->format('Y-m-d') === $sportCurrentMonday;
$isSportToday = $date === $sportToday;
$sportPrevWeek = $sportMonday->modify('-7 days')->format('Y-m-d');
$sportNextSame = $sportSelected->modify('+7 days')->format('Y-m-d');
$sportNextWeek = $sportNextSame > $sportToday ? $sportToday : $sportNextSame;
$canGoNextSportWeek = $sportMonday->format('Y-m-d') < $sportCurrentMonday;
$sportDayNames = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo',
];
$sportDayUrl = static function (string $day) use ($sportToday): string {
    if ($day === '' || $day === $sportToday) {
        return url('/deportes');
    }

    return url('/deportes?date=' . $day);
};
?>
<section class="page-header with-actions">
    <div>
        <h1>Entrenamiento</h1>
        <p class="muted">Disciplinas, peso y rutina. Esto no se mezcla con los hábitos.</p>
    </div>
    <button type="button" class="btn btn-primary" data-open-modal="sportModal">+ Disciplina</button>
</section>

<section class="kpi-grid sport-kpis">
    <article class="card kpi-card kpi-card--peach">
        <div class="kpi-label">Peso</div>
        <div class="kpi-value"><?= $board['latest_weight'] !== null ? e($fmt((float) $board['latest_weight'])) : '—' ?><?php if ($board['latest_weight'] !== null): ?><span class="kpi-unit"> kg</span><?php endif; ?></div>
        <div class="muted small">
            <?php if ($board['weight_delta'] === null): ?>
                Sin comparación todavía
            <?php else: ?>
                <?= (float) $board['weight_delta'] > 0 ? '+' : '' ?><?= e($fmt((float) $board['weight_delta'])) ?> kg vs. el anterior
            <?php endif; ?>
        </div>
    </article>
    <article class="card kpi-card kpi-card--lavender">
        <div class="kpi-label">BMI</div>
        <div class="kpi-value"><?= $board['bmi'] !== null ? e($fmt((float) $board['bmi'])) : '—' ?></div>
        <div class="muted small"><?= e((string) $board['bmi_label']) ?></div>
    </article>
    <article class="card kpi-card kpi-card--mint">
        <div class="kpi-label">Km esta semana</div>
        <div class="kpi-value"><?= e($fmt((float) $metrics['km_week'])) ?><span class="kpi-unit"> km</span></div>
        <div class="muted small">Suma de lo que corriste</div>
    </article>
    <article class="card kpi-card kpi-card--primary">
        <div class="kpi-label">Clases</div>
        <div class="kpi-value"><?= (int) $metrics['classes_done'] ?><span class="kpi-unit"> / <?= (int) $metrics['classes_planned'] ?></span></div>
        <div class="muted small">De esta semana</div>
    </article>
    <article class="card kpi-card kpi-card--yellow">
        <div class="kpi-label">Rutina de hoy</div>
        <div class="kpi-value">
            <?php if (!$hasRoutine): ?>
                —
            <?php else: ?>
                <?= (int) $metrics['routine_done'] ?><span class="kpi-unit"> / <?= (int) $metrics['routine_total'] ?></span>
            <?php endif; ?>
        </div>
        <div class="muted small"><?= $hasRoutine ? 'Ejercicios marcados' . ($repsTotal > 0 ? ' · ' . $repsAtGoal . '/' . $repsTotal . ' en ×100' : '') : 'Sin rutina personal' ?></div>
    </article>
</section>

<div class="habit-day-bar sport-day-bar">
    <label class="habit-day-select-wrap" for="sportDaySelect">
        <span class="muted small">Día</span>
        <select id="sportDaySelect" class="habit-day-select" data-day-select aria-label="Elegir día">
            <?php foreach ($week['days'] as $day):
                if ((string) $day['date'] > $sportToday) {
                    continue;
                }
                $dayLabel = ($sportDayNames[(int) $day['dow']] ?? (string) $day['label'])
                    . ' '
                    . (new DateTimeImmutable((string) $day['date']))->format('j/n');
                ?>
                <option value="<?= e($sportDayUrl((string) $day['date'])) ?>"<?= (string) $day['date'] === $date ? ' selected' : '' ?>>
                    <?= e($dayLabel) ?><?= !empty($day['is_today']) ? ' · hoy' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="sport-day-tools">
        <p class="sport-series-week muted small">Esta semana <strong><?= number_format((int) ($metrics['series_week'] ?? 0), 0, ',', '.') ?></strong> series</p>
        <div class="btn-row habit-week-nav">
            <a class="btn btn-ghost btn-sm" href="<?= e($sportDayUrl($sportPrevWeek)) ?>" title="Semana anterior">←</a>
            <?php if (!$isCurrentSportWeek || !$isSportToday): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/deportes')) ?>">Hoy</a>
            <?php endif; ?>
            <?php if ($canGoNextSportWeek): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e($sportDayUrl($sportNextWeek)) ?>" title="Semana siguiente">→</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="sport-layout">
    <div class="sport-side">
    <section class="card sport-card">
        <h2>Cuerpo</h2>
        <form method="post" action="<?= e(form_action('/deportes/body')) ?>" class="stack-form">
            <?= csrf_field() ?>
            <?= route_field('/deportes/body') ?>
            <input type="hidden" name="date" value="<?= e($date) ?>">
            <div class="sport-body-grid">
                <label class="field">
                    <span>Altura (cm)</span>
                    <input type="number" name="height_cm" min="80" max="250" step="0.1" inputmode="decimal" value="<?= $height !== null ? e((string) $height) : '' ?>" placeholder="175">
                </label>
                <label class="field">
                    <span>Peso de este día (kg)</span>
                    <input type="number" name="weight_kg" min="25" max="350" step="0.1" inputmode="decimal" placeholder="72.5">
                </label>
            </div>
            <button type="submit" class="btn btn-primary">Guardar medidas</button>
        </form>
        <?php if ($board['weights'] !== []): ?>
            <p class="sport-weight-caption muted small">Últimos registros</p>
            <ul class="sport-weight-list">
                <?php foreach ($board['weights'] as $row): ?>
                    <li>
                        <span><?= e(format_date((string) $row['weighed_on'], 'd/m')) ?></span>
                        <strong><?= e($fmt((float) $row['weight_kg'])) ?> kg</strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card sport-card">
        <h2>Disciplinas</h2>
        <?php if ($board['disciplines'] === []): ?>
            <p class="empty-state">Sumá correr, una clase o lo que entrenes. Cada una se anota solo acá.</p>
        <?php endif; ?>
        <ul class="sport-disciplines">
            <?php foreach ($board['disciplines'] as $disc):
                $dayNames = [];
                foreach ($disc['weekdays'] as $dow) {
                    $dayNames[] = $dayShort[(int) $dow] ?? '';
                }
                ?>
                <li class="sport-discipline">
                    <div class="sport-discipline-copy">
                        <strong><?= e((string) $disc['name']) ?></strong>
                        <span class="muted small">
                            <?= $disc['kind'] === 'distance' ? 'Kilómetros' : 'Clase' ?>
                            · <?= e(implode(' ', $dayNames)) ?>
                            <?php if ($disc['kind'] === 'distance' && $disc['target_amount'] !== null): ?>
                                · <?= e($fmt((float) $disc['target_amount'])) ?> km por día
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="sport-discipline-action">
                        <div class="sport-discipline-slot">
                        <?php if ($disc['kind'] === 'distance'): ?>
                            <form method="post" action="<?= e(form_action('/deportes/' . $disc['id'] . '/log')) ?>" class="sport-km-form">
                                <?= csrf_field() ?>
                                <?= route_field('/deportes/' . $disc['id'] . '/log') ?>
                                <input type="hidden" name="date" value="<?= e($date) ?>">
                                <label>
                                    <span class="sr-only">Kilómetros</span>
                                    <input type="number" name="quantity" min="0" max="300" step="0.1" inputmode="decimal" value="<?= $disc['quantity'] !== null ? e((string) $disc['quantity']) : '' ?>" placeholder="0">
                                </label>
                                <button type="submit" class="btn btn-ghost btn-sm">km</button>
                            </form>
                            <?php if (!$disc['planned_today']): ?><span class="muted small">Día extra</span><?php endif; ?>
                        <?php elseif ($disc['planned_today']): ?>
                            <form method="post" action="<?= e(form_action('/deportes/' . $disc['id'] . '/log')) ?>">
                                <?= csrf_field() ?>
                                <?= route_field('/deportes/' . $disc['id'] . '/log') ?>
                                <input type="hidden" name="date" value="<?= e($date) ?>">
                                <input type="hidden" name="attended" value="<?= $disc['attended'] ? '0' : '1' ?>">
                                <button type="submit" class="btn <?= $disc['attended'] ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $disc['attended'] ? 'Fui' : 'Marcar que fui' ?></button>
                            </form>
                        <?php else: ?>
                            <span class="muted small">Sin clase este día</span>
                        <?php endif; ?>
                        </div>
                        <form method="post" action="<?= e(form_action('/deportes/' . $disc['id'] . '/delete')) ?>" onsubmit="return confirm('¿Sacar esta disciplina?')">
                            <?= csrf_field() ?>
                            <?= route_field('/deportes/' . $disc['id'] . '/delete') ?>
                            <input type="hidden" name="date" value="<?= e($date) ?>">
                            <button type="submit" class="icon-btn" aria-label="Eliminar disciplina">×</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    </div>

    <section class="card sport-card">
        <div class="sport-card-head">
            <h2>Rutina personal</h2>
            <form method="post" action="<?= e(form_action('/deportes/routine')) ?>">
                <?= csrf_field() ?>
                <?= route_field('/deportes/routine') ?>
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="has_routine" value="<?= $hasRoutine ? '0' : '1' ?>">
                <button type="submit" class="btn btn-ghost btn-sm"><?= $hasRoutine ? 'No tengo rutina' : 'Tengo rutina' ?></button>
            </form>
        </div>
        <?php if (!$hasRoutine): ?>
            <p class="muted">Si entrenás por tu cuenta, armá una lista y tachala cada día. No entra en Hábitos.</p>
        <?php else: ?>
            <form method="post" action="<?= e(form_action('/deportes/exercises')) ?>" class="sport-inline-add">
                <?= csrf_field() ?>
                <?= route_field('/deportes/exercises') ?>
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="text" name="name" required maxlength="120" placeholder="Ej. Sentadillas, plancha">
                <label class="sport-reps-field">
                    <span class="sport-reps-mark" aria-hidden="true">×</span>
                    <span class="sr-only">Cantidad, de 10 a 100</span>
                    <select name="reps" required>
                        <?php foreach (SportService::repsOptions() as $amount): ?>
                            <option value="<?= $amount ?>"<?= $amount === 10 ? ' selected' : '' ?>><?= $amount ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary">Sumar</button>
            </form>
            <?php if ($repsTotal > 0): ?>
                <div class="sport-reps-goal">
                    <div class="progress"><span style="width:<?= $repsPct ?>%;background:var(--color-mint)"></span></div>
                    <span class="muted small"><?= $repsAtGoal ?> de <?= $repsTotal ?> en ×100 · <?= $repsPct ?>%</span>
                </div>
            <?php endif; ?>
            <ul class="habit-list sport-check-list sport-routine-scroll" data-exercise-order>
                <?php if ($board['exercises'] === []): ?>
                    <li class="empty-state">Todavía no hay ejercicios.</li>
                <?php endif; ?>
                <?php foreach ($board['exercises'] as $index => $exercise): ?>
                    <li
                        class="habit-row sport-exercise<?= $exercise['done'] ? ' is-done' : '' ?>"
                        data-exercise-id="<?= (int) $exercise['id'] ?>"
                        data-color-group="<?= habit_color_group($index + 1) ?>"
                    >
                        <span class="habit-drag" data-drag-handle title="Arrastrar para reordenar" aria-label="Arrastrar para reordenar">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
                        </span>
                        <span class="habit-num"><?= $index + 1 ?></span>
                        <span class="habit-meta">
                            <form method="post" action="<?= e(form_action('/deportes/exercises/' . $exercise['id'] . '/rename')) ?>" class="sport-rename">
                                <?= csrf_field() ?>
                                <?= route_field('/deportes/exercises/' . $exercise['id'] . '/rename') ?>
                                <input type="hidden" name="date" value="<?= e($date) ?>">
                                <label>
                                    <span class="sr-only">Nombre de <?= e((string) $exercise['name']) ?></span>
                                    <input class="sport-name-input habit-name" type="text" name="name" required maxlength="120" value="<?= e((string) $exercise['name']) ?>" aria-label="Editar <?= e((string) $exercise['name']) ?>">
                                </label>
                            </form>
                        </span>
                        <form method="post" action="<?= e(form_action('/deportes/exercises/' . $exercise['id'] . '/reps')) ?>" class="sport-reps-form">
                            <?= csrf_field() ?>
                            <?= route_field('/deportes/exercises/' . $exercise['id'] . '/reps') ?>
                            <input type="hidden" name="date" value="<?= e($date) ?>">
                            <span class="sport-reps-mark" aria-hidden="true">×</span>
                            <label>
                                <span class="sr-only">Cantidad de <?= e((string) $exercise['name']) ?></span>
                                <select name="reps" onchange="this.form.submit()">
                                    <?php foreach (SportService::repsOptions() as $amount): ?>
                                        <option value="<?= $amount ?>"<?= (int) $exercise['reps'] === $amount ? ' selected' : '' ?>><?= $amount ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </form>
                        <form method="post" action="<?= e(form_action('/deportes/exercises/' . $exercise['id'] . '/delete')) ?>" onsubmit="return confirm('¿Sacar este ejercicio de la rutina?')">
                            <?= csrf_field() ?>
                            <?= route_field('/deportes/exercises/' . $exercise['id'] . '/delete') ?>
                            <input type="hidden" name="date" value="<?= e($date) ?>">
                            <button type="submit" class="icon-btn" aria-label="Eliminar">×</button>
                        </form>
                        <form method="post" action="<?= e(form_action('/deportes/exercises/' . $exercise['id'] . '/toggle')) ?>" class="habit-toggle-form">
                            <?= csrf_field() ?>
                            <?= route_field('/deportes/exercises/' . $exercise['id'] . '/toggle') ?>
                            <input type="hidden" name="date" value="<?= e($date) ?>">
                            <label class="check-toggle" title="Marcar hecho">
                                <input type="checkbox" <?= $exercise['done'] ? 'checked' : '' ?> onchange="this.form.submit()" aria-label="<?= $exercise['done'] ? 'Desmarcar' : 'Marcar' ?> <?= e((string) $exercise['name']) ?>">
                                <span></span>
                            </label>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<dialog class="modal" id="sportModal" aria-labelledby="sportModalTitle">
    <form method="post" action="<?= e(form_action('/deportes')) ?>" class="stack-form sport-form" id="sportForm">
        <?= csrf_field() ?>
        <?= route_field('/deportes') ?>
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <div class="modal-head">
            <h2 id="sportModalTitle">Nueva disciplina</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </div>
        <label class="field">
            <span>Nombre</span>
            <input type="text" name="name" required maxlength="120" placeholder="Ej. Correr, jiu-jitsu, yoga">
        </label>
        <fieldset class="sport-kind">
            <legend>Tipo</legend>
            <label class="task-repeat-chip">
                <input type="radio" name="kind" value="distance" checked>
                <span>Kilómetros</span>
            </label>
            <label class="task-repeat-chip">
                <input type="radio" name="kind" value="class">
                <span>Clase</span>
            </label>
        </fieldset>
        <label class="field sport-target">
            <span>Kilómetros por día</span>
            <input type="number" name="target_amount" min="0.1" max="200" step="0.1" inputmode="decimal" placeholder="5">
        </label>
        <fieldset class="task-repeat-picker">
            <legend class="sr-only">Días</legend>
            <span class="task-repeat-label" aria-hidden="true">Días</span>
            <?php foreach ($dayShort as $dow => $label): ?>
                <label class="task-repeat-chip">
                    <input type="checkbox" name="weekdays[]" value="<?= (int) $dow ?>">
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="muted small">Las clases y los kilómetros quedan solo en Entrenamiento.</p>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</dialog>
