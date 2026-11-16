<?php
/** @var string $weekStart */
/** @var string $weekEnd */
/** @var string $prevWeek */
/** @var string $nextWeek */
/** @var string $weekNumber */
/** @var string $weekYear */
/** @var string $rangeLabel */
/** @var bool $isCurrentWeek */
/** @var list<array<string,mixed>> $tasks */
/** @var int $tasksDone */
/** @var list<array<string,mixed>> $training */
/** @var int $trainingCount */
/** @var list<array<string,mixed>> $habits */
/** @var int $habitsDoneCount */
/** @var list<array<string,mixed>> $goals */
/** @var list<array<string,mixed>> $milestones */
/** @var list<array<string,mixed>> $horizons */
/** @var int $completionsCount */

$base = '/informe-semanal';
$dayNames = [
    1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom',
];
?>
<section class="page-header with-actions">
    <div>
        <p class="eyebrow">Lunes a domingo</p>
        <h1>Informe semanal</h1>
        <p class="muted">
            Semana <?= e(ltrim((string) $weekNumber, '0') ?: '0') ?> de <?= e((string) $weekYear) ?>
            · <?= e((string) $rangeLabel) ?>
            <?php if (!empty($isCurrentWeek)): ?> · Esta semana<?php endif; ?>
        </p>
    </div>
    <div class="btn-row week-report-actions">
        <a class="btn btn-ghost" href="<?= e(url($base . '?week=' . $prevWeek)) ?>">← Anterior</a>
        <a class="btn btn-ghost" href="<?= e(url($base)) ?>">Hoy</a>
        <a class="btn btn-ghost" href="<?= e(url($base . '?week=' . $nextWeek)) ?>">Siguiente →</a>
    </div>
</section>

<div class="metric-grid metric-grid--weekly">
    <div class="metric">
        <span>Tareas hechas</span>
        <strong><?= (int) $tasksDone ?></strong>
    </div>
    <div class="metric">
        <span>Entrenamiento</span>
        <strong><?= (int) $trainingCount ?></strong>
    </div>
    <div class="metric">
        <span>Hábitos hechos</span>
        <strong><?= (int) $habitsDoneCount ?></strong>
    </div>
    <div class="metric">
        <span>Logros</span>
        <strong><?= (int) $completionsCount ?></strong>
    </div>
</div>

<section class="card week-report-cut week-report-cut--task">
    <div class="section-head">
        <h2>Plan semanal</h2>
        <strong><?= (int) $tasksDone ?>/<?= count($tasks) ?></strong>
    </div>
    <?php if ($tasks === []): ?>
        <p class="empty-note">Ninguna esta semana.</p>
    <?php else: ?>
        <ul class="week-report-list">
            <?php foreach ($tasks as $row):
                $done = (int) ($row['is_done'] ?? 0) === 1;
                $dow = (int) (new DateTimeImmutable((string) $row['task_date']))->format('N');
                $kind = (($row['task_kind'] ?? '') === 'work') ? 'Laboral' : 'Personal';
                ?>
                <li class="<?= $done ? 'is-done' : '' ?>">
                    <strong><?= e((string) $row['title']) ?></strong>
                    <span class="muted">
                        <?= e($dayNames[$dow] ?? '') ?> <?= e(format_date((string) $row['task_date'], 'd/m')) ?>
                        · <?= e($kind) ?>
                        <?php if ($done): ?> · Hecha<?php else: ?> · Pendiente<?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card week-report-cut week-report-cut--sport">
    <div class="section-head">
        <h2>Entrenamiento</h2>
        <strong><?= (int) $trainingCount ?></strong>
    </div>
    <?php if ($training === []): ?>
        <p class="empty-note">Nada registrado esta semana.</p>
    <?php else: ?>
        <ul class="week-report-list">
            <?php foreach ($training as $row): ?>
                <li>
                    <strong><?= e((string) $row['title']) ?></strong>
                    <span class="muted"><?= e((string) ($row['meta'] ?? '')) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card week-report-cut week-report-cut--habit">
    <div class="section-head">
        <h2>Hábitos y repeticiones</h2>
        <strong><?= (int) $habitsDoneCount ?></strong>
    </div>
    <?php if ($habits === []): ?>
        <p class="empty-note">Sin hábitos marcados esta semana.</p>
    <?php else: ?>
        <ul class="week-report-list">
            <?php foreach ($habits as $row):
                $times = (int) ($row['times'] ?? 0);
                $qty = (float) ($row['qty_sum'] ?? 0);
                ?>
                <li>
                    <strong><?= e((string) $row['title']) ?></strong>
                    <span class="muted">
                        <?= $times ?> <?= $times === 1 ? 'vez' : 'veces' ?>
                        <?php if ($qty > 0): ?>
                            · acumulado <?= e(number_format($qty, $qty == floor($qty) ? 0 : 1, ',', '.')) ?>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card week-report-cut week-report-cut--goal">
    <h2>Objetivos completados</h2>
    <?php if ($goals === []): ?>
        <p class="empty-note">Ninguno esta semana.</p>
    <?php else: ?>
        <ul class="week-report-list">
            <?php foreach ($goals as $row): ?>
                <li>
                    <strong><?= e((string) $row['title']) ?></strong>
                    <?php $when = format_when($row['completed_at'] ?? ''); if ($when !== ''): ?>
                        <span class="muted"><?= e($when) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card week-report-cut week-report-cut--milestone">
    <h2>Hitos completados</h2>
    <?php if ($milestones === []): ?>
        <p class="empty-note">Ninguno esta semana.</p>
    <?php else: ?>
        <ul class="week-report-list">
            <?php foreach ($milestones as $row): ?>
                <li>
                    <strong><?= e((string) $row['title']) ?></strong>
                    <span class="muted">
                        <?php if (!empty($row['milestone_date'])): ?>
                            fecha <?= e(format_date((string) $row['milestone_date'], 'd/m')) ?>
                        <?php endif; ?>
                        <?php $when = format_when($row['completed_at'] ?? ''); if ($when !== ''): ?>
                            <?= !empty($row['milestone_date']) ? ' · ' : '' ?><?= e($when) ?>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card week-report-cut week-report-cut--horizon">
    <h2>Horizontes completados</h2>
    <?php if ($horizons === []): ?>
        <p class="empty-note">Ninguno esta semana.</p>
    <?php else: ?>
        <ul class="week-report-list">
            <?php foreach ($horizons as $row): ?>
                <li>
                    <strong><?= e((string) $row['title']) ?></strong>
                    <?php $when = format_when($row['completed_at'] ?? ''); if ($when !== ''): ?>
                        <span class="muted"><?= e($when) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
