<?php
/** @var array $filters */
/** @var array $overview */
/** @var list<array> $areas */
/** @var array $weeklyChart */
/** @var array $areaChart */
/** @var array $heatmapChart */
$year = (int) ($filters['year'] ?? now_local()->format('Y'));
$areaSnap = $overview['areas'] ?? [];
$prevSnap = $overview['prev_year'] ?? [];
$prevByKey = [];
$prevById = [];
foreach ($prevSnap as $row) {
    $prevByKey[(string) $row['area_key']] = $row;
    if (!empty($row['area_id'])) {
        $prevById[(int) $row['area_id']] = $row;
    }
}
$books = $overview['books'] ?? ['finished' => 0, 'pages' => 0, 'planned' => 0, 'reading' => 0, 'target' => 12];
$availableYears = !empty($availableYears) ? $availableYears : [$year];
$catalogMetrics = $overview['catalog_metrics'] ?? ['consolidated' => [], 'areas' => []];
$bookTarget = max(1, (int) ($books['target'] ?? 12));
$consolidatedMetrics = $catalogMetrics['consolidated'] ?? [];
$areaMetrics = $catalogMetrics['areas'] ?? [];
$weeklyPlan = $overview['weekly_plan'] ?? [
    'current' => ['week_percent' => 0, 'week_done' => 0, 'week_total' => 0, 'days_complete' => 0, 'today_percent' => 0],
    'avg_completion' => 0,
    'perfect_days' => 0,
    'tasks_done' => 0,
    'tasks_total' => 0,
];
$wpCurrent = $weeklyPlan['current'] ?? [];
$ms = $overview['milestones'] ?? ['upcoming' => 0, 'pending_due' => 0, 'done' => 0, 'total' => 0];
$msTotal = max(0, (int) ($ms['total'] ?? 0));
$msDone = (int) ($ms['done'] ?? 0);
$msPct = $msTotal > 0 ? round(($msDone / $msTotal) * 100) : 0;
$water = $overview['water'] ?? [
    'today_ml' => 0,
    'target_ml' => 2000,
    'today_done' => false,
    'week_days_done' => 0,
    'week_days' => 0,
    'month_days_done' => 0,
    'month_days' => 0,
    'year_days_done' => 0,
    'year_days' => 0,
    'avg_week_ml' => 0,
    'streak' => 0,
];
$waterTodayPct = max(0, min(100, round(((float) ($water['today_ml'] ?? 0) / max(1.0, (float) ($water['target_ml'] ?? 2000))) * 100)));
$waterWeekPct = ((int) ($water['week_days'] ?? 0)) > 0
    ? round(((int) ($water['week_days_done'] ?? 0) / max(1, (int) $water['week_days'])) * 100)
    : 0;
$waterMonthPct = ((int) ($water['month_days'] ?? 0)) > 0
    ? round(((int) ($water['month_days_done'] ?? 0) / max(1, (int) $water['month_days'])) * 100)
    : 0;
$friends = $overview['friends'] ?? [
    'total' => 0,
    'talked_week' => 0,
    'talks_week' => 0,
    'talked_month' => 0,
    'talks_month' => 0,
    'talked_year' => 0,
    'talks_year' => 0,
    'never' => 0,
    'coverage_month' => 0,
    'days_week' => 0,
    'streak' => 0,
    'last_talk_date' => null,
    'suggested' => null,
];
$friendsTotal = max(0, (int) ($friends['total'] ?? 0));
$friendsCoverage = (int) ($friends['coverage_month'] ?? 0);
$fruit = $overview['fruit'] ?? [
    'today' => 0,
    'target' => 3,
    'today_done' => false,
    'week_days_done' => 0,
    'week_days' => 7,
    'month_days_done' => 0,
    'month_days' => 0,
    'year_days_done' => 0,
    'year_days' => 0,
    'avg_week' => 0,
    'streak' => 0,
];
$fruitTodayPct = max(0, min(100, round(((float) ($fruit['today'] ?? 0) / max(1.0, (float) ($fruit['target'] ?? 3))) * 100)));
$fruitWeekPct = ((int) ($fruit['week_days'] ?? 0)) > 0
    ? round(((int) ($fruit['week_days_done'] ?? 0) / max(1, (int) $fruit['week_days'])) * 100)
    : 0;
$fruitMonthPct = ((int) ($fruit['month_days'] ?? 0)) > 0
    ? round(((int) ($fruit['month_days_done'] ?? 0) / max(1, (int) $fruit['month_days'])) * 100)
    : 0;
$areaCount = count($areaSnap);
$areaCountSafe = max(1, $areaCount);

$maxProgress = 0.0;
$minProgress = 100.0;
$maxName = '—';
$minName = '—';
$activeAreas = 0;
foreach ($areaSnap as $a) {
    if ($a['total'] > 0) {
        $activeAreas++;
    }
    if ($a['avg_progress'] >= $maxProgress) {
        $maxProgress = (float) $a['avg_progress'];
        $maxName = $a['name'];
    }
    if ($a['avg_progress'] <= $minProgress) {
        $minProgress = (float) $a['avg_progress'];
        $minName = $a['name'];
    }
}

// Año de comparación: respetar el pedido del usuario (o el calculado en overview).
$compareYear = (int) ($filters['compare_year'] ?? $overview['compare_year'] ?? ($year - 1));
if ($compareYear === $year || $compareYear < 2000 || $compareYear > 2100) {
    $compareYear = (int) ($overview['compare_year'] ?? ($year - 1));
}
if ($compareYear === $year) {
    $compareYear = $year - 1;
}

$compareOptions = [];
foreach ($availableYears as $cy) {
    $cy = (int) $cy;
    if ($cy !== $year && $cy >= 2000 && $cy <= 2100) {
        $compareOptions[] = $cy;
    }
}
foreach ([$compareYear, $year - 1] as $extra) {
    $extra = (int) $extra;
    if ($extra !== $year && $extra >= 2000 && $extra <= 2100 && !in_array($extra, $compareOptions, true)) {
        $compareOptions[] = $extra;
    }
}
$compareOptions = array_values(array_unique($compareOptions));
rsort($compareOptions, SORT_NUMERIC);
if ($compareOptions === []) {
    $compareOptions[] = $year - 1;
}
?>
<section class="page-header">
    <div>
        <h1>Métricas</h1>
        <p class="muted">Vista consolidada de tus <?= (int) $areaCount ?> áreas · todo sobre el año seleccionado</p>
    </div>
</section>

<form method="get" action="<?= e(base_path() . '/index.php') ?>" class="metrics-year-controls">
    <input type="hidden" name="r" value="/metrics">
    <label class="field metrics-year-field">
        <span>Año</span>
        <select name="year" onchange="this.form.submit()" aria-label="Año a visualizar">
            <?php foreach ($availableYears as $y): ?>
                <option value="<?= (int) $y ?>" <?= (int) $y === $year ? 'selected' : '' ?>><?= (int) $y ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="field metrics-year-field">
        <span>Comparar con</span>
        <select name="compare_year" onchange="this.form.submit()" aria-label="Año de comparación">
            <?php foreach ($compareOptions as $cy): ?>
                <option value="<?= (int) $cy ?>" <?= (int) $cy === $compareYear ? 'selected' : '' ?>">vs <?= (int) $cy ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<section class="metrics-kpi-layout">
    <?php
    $monthlyBar = $overview['monthly_completion'] ?? [];
    $nowLocal = now_local();
    $currentYear = (int) $nowLocal->format('Y');
    $yearNowPct = null;
    if ($year < $currentYear) {
        $yearNowPct = 100.0;
    } elseif ($year === $currentYear) {
        $daysTotal = (int) (new DateTimeImmutable($year . '-12-31', $nowLocal->getTimezone()))->format('z') + 1;
        $daysElapsed = (int) $nowLocal->format('z') + 1;
        $yearNowPct = $daysTotal > 0 ? round(($daysElapsed / $daysTotal) * 100, 2) : 0.0;
    }
    ?>
    <article class="card kpi-card kpi-card--mint kpi-card--featured kpi-card--completeness">
        <div class="kpi-featured-main">
            <div class="kpi-label">Completitud <?= (int) $year ?></div>
            <div class="kpi-value"><?= e(number_format((float) $overview['completion_rate'], 0)) ?>%</div>
            <div class="muted small"><?= (int) $overview['goals_completed'] ?> / <?= (int) $overview['goals_total'] ?> objetivos</div>
        </div>
        <div class="completeness-bar-wrap">
            <div class="completeness-track">
                <div class="completeness-bar" role="img" aria-label="Completitud mensual de objetivos">
                    <?php foreach ($monthlyBar as $seg):
                        $pct = min(100.0, max(0.0, (float) $seg['percent']));
                        $opacity = $pct <= 0 ? 0.22 : (0.28 + ($pct / 100) * 0.72);
                        ?>
                        <div
                            class="completeness-seg"
                            style="flex-grow:<?= (int) $seg['days'] ?>;--seg-color:<?= e((string) $seg['color']) ?>;--seg-opacity:<?= e(number_format($opacity, 2, '.', '')) ?>"
                            title="<?= e($seg['label'] . ': ' . number_format($pct, 0) . '% (' . (int) $seg['checked'] . '/' . (int) $seg['total'] . ')') ?>"
                        ></div>
                    <?php endforeach; ?>
                </div>
                <?php if ($yearNowPct !== null): ?>
                    <span
                        class="completeness-now"
                        style="--now-pct:<?= e(number_format(min(100, max(0, $yearNowPct)), 2, '.', '')) ?>%"
                        title="<?= $year === $currentYear
                            ? 'Hoy · ' . number_format((float) $yearNowPct, 0) . '% del año'
                            : 'Fin de ' . (int) $year ?>"
                        aria-hidden="true"
                    ></span>
                <?php endif; ?>
            </div>
            <div class="completeness-labels" aria-hidden="true">
                <?php foreach ($monthlyBar as $seg): ?>
                    <span style="flex-grow:<?= (int) $seg['days'] ?>"><?= e((string) $seg['label']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </article>
    <div class="kpi-grid kpi-grid-3">
        <article class="card kpi-card kpi-card--lavender">
            <div class="kpi-label">Progreso promedio</div>
            <div class="kpi-value"><?= e(number_format((float) $overview['avg_progress'], 0)) ?>%</div>
            <div class="progress kpi-bar"><span style="width:<?= e((string) min(100, (float) $overview['avg_progress'])) ?>%"></span></div>
            <div class="muted small"><?= (int) $overview['goals_active'] ?> activos · <?= (int) $overview['goals_at_risk'] ?> en riesgo</div>
        </article>
        <article class="card kpi-card kpi-card--peach">
            <div class="kpi-label">Áreas activas</div>
            <div class="kpi-value"><?= $activeAreas ?>/<?= $areaCountSafe ?></div>
            <div class="progress kpi-bar"><span style="width:<?= e((string) round(($activeAreas / $areaCountSafe) * 100)) ?>%"></span></div>
            <div class="muted small">Mayor: <?= e($maxName) ?> · Menor: <?= e($minName) ?></div>
        </article>
        <article class="card kpi-card kpi-card--yellow">
            <div class="kpi-label">Libros</div>
            <div class="kpi-value"><?= (int) $books['finished'] ?>/<?= $bookTarget ?></div>
            <div class="progress kpi-bar"><span style="width:<?= e((string) min(100, ((int) $books['finished'] / $bookTarget) * 100)) ?>%"></span></div>
            <div class="muted small"><?= (int) $books['pages'] ?> págs · <?= (int) $books['planned'] ?> planeados</div>
        </article>
    </div>
</section>

<section class="card" style="margin-top:16px">
    <div class="card-header">
        <h2>Plan semanal</h2>
        <a class="text-link" href="<?= e(url('/weekly')) ?>">Ver plan →</a>
    </div>
    <div class="kpi-grid kpi-grid-4" style="margin:0">
        <article class="metrics-area-card">
            <header><strong>Esta semana</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= e(number_format((float) ($wpCurrent['week_percent'] ?? 0), 0)) ?>%</div>
            <div class="progress"><span style="width:<?= e((string) min(100, (float) ($wpCurrent['week_percent'] ?? 0))) ?>%;background:var(--color-mint)"></span></div>
            <div class="muted small" style="margin-top:8px"><?= (int) ($wpCurrent['week_done'] ?? 0) ?> / <?= (int) ($wpCurrent['week_total'] ?? 0) ?> tareas</div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Hoy</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= e(number_format((float) ($wpCurrent['today_percent'] ?? 0), 0)) ?>%</div>
            <div class="progress"><span style="width:<?= e((string) min(100, (float) ($wpCurrent['today_percent'] ?? 0))) ?>%;background:var(--color-primary)"></span></div>
            <div class="muted small" style="margin-top:8px"><?= (int) ($wpCurrent['today_done'] ?? 0) ?> / <?= (int) ($wpCurrent['today_total'] ?? 0) ?> del día</div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Días 100%</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($weeklyPlan['perfect_days'] ?? 0) ?></div>
            <div class="muted small" style="margin-top:8px">Últimas 4 semanas</div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Promedio diario</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= e(number_format((float) ($weeklyPlan['avg_completion'] ?? 0), 0)) ?>%</div>
            <div class="muted small" style="margin-top:8px"><?= (int) ($weeklyPlan['tasks_done'] ?? 0) ?> / <?= (int) ($weeklyPlan['tasks_total'] ?? 0) ?> tareas</div>
        </article>
    </div>
</section>

<section class="card" style="margin-top:16px">
    <div class="card-header">
        <h2>Hitos</h2>
        <a class="text-link" href="<?= e(url('/milestones')) ?>">Ver hitos →</a>
    </div>
    <div class="kpi-grid kpi-grid-4" style="margin:0">
        <article class="metrics-area-card">
            <header><strong>Pendientes</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($ms['pending_due'] ?? 0) ?></div>
            <div class="muted small" style="margin-top:8px">Hoy o vencidos (bloquean Hoy)</div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Próximos</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($ms['upcoming'] ?? 0) ?></div>
            <div class="muted small" style="margin-top:8px">Todavía no llegaron</div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Hechos</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= $msDone ?></div>
            <div class="progress"><span style="width:<?= e((string) min(100, $msPct)) ?>%;background:var(--color-mint)"></span></div>
            <div class="muted small" style="margin-top:8px"><?= $msPct ?>% de <?= $msTotal ?></div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Total</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= $msTotal ?></div>
            <div class="muted small" style="margin-top:8px">Cargados en Hitos</div>
        </article>
    </div>
</section>

<section class="card" style="margin-top:16px">
    <div class="card-header">
        <h2>Agua</h2>
        <a class="text-link" href="<?= e(url('/today')) ?>">Registrar en Hoy →</a>
    </div>
    <div class="kpi-grid kpi-grid-4" style="margin:0">
        <article class="metrics-area-card">
            <header><strong>Hoy</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0">
                <?= e(number_format((float) ($water['today_ml'] ?? 0), 0, ',', '.')) ?>
                <span class="muted" style="font-size:0.85rem;font-weight:600">ml</span>
            </div>
            <div class="progress"><span style="width:<?= (int) $waterTodayPct ?>%;background:var(--color-primary)"></span></div>
            <div class="muted small" style="margin-top:8px">
                de <?= e(number_format((float) ($water['target_ml'] ?? 2000), 0, ',', '.')) ?> ml
                <?= !empty($water['today_done']) ? '· meta ok' : '' ?>
            </div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Esta semana</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($water['week_days_done'] ?? 0) ?>/<?= (int) ($water['week_days'] ?? 0) ?></div>
            <div class="progress"><span style="width:<?= (int) $waterWeekPct ?>%;background:var(--color-mint)"></span></div>
            <div class="muted small" style="margin-top:8px">días con 2 L de 7 · prom. <?= e(number_format((float) ($water['avg_week_ml'] ?? 0), 0, ',', '.')) ?> ml</div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Este mes</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($water['month_days_done'] ?? 0) ?>/<?= (int) ($water['month_days'] ?? 0) ?></div>
            <div class="progress"><span style="width:<?= (int) $waterMonthPct ?>%;background:var(--color-peach)"></span></div>
            <div class="muted small" style="margin-top:8px"><?= (int) $waterMonthPct ?>% de los <?= (int) ($water['month_days'] ?? 0) ?> días (meta 2 L/día)</div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Racha</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($water['streak'] ?? 0) ?></div>
            <div class="muted small" style="margin-top:8px">días seguidos · <?= (int) ($water['year_days_done'] ?? 0) ?> en el año</div>
        </article>
    </div>
</section>

<section class="card" style="margin-top:16px">
    <div class="card-header">
        <h2>Frutas</h2>
        <a class="text-link" href="<?= e(url('/today')) ?>">Registrar en Hoy →</a>
    </div>
    <div class="kpi-grid kpi-grid-4" style="margin:0">
        <article class="metrics-area-card">
            <header><strong>Hoy</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0">
                <?= (int) ($fruit['today'] ?? 0) ?>
                <span class="muted" style="font-size:0.85rem;font-weight:600">/ <?= (int) ($fruit['target'] ?? 3) ?></span>
            </div>
            <div class="progress"><span style="width:<?= (int) $fruitTodayPct ?>%;background:var(--color-peach)"></span></div>
            <div class="muted small" style="margin-top:8px">
                banana · mandarina · naranja
                <?= !empty($fruit['today_done']) ? ' · meta ok' : '' ?>
            </div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Esta semana</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($fruit['week_days_done'] ?? 0) ?>/<?= (int) ($fruit['week_days'] ?? 7) ?></div>
            <div class="progress"><span style="width:<?= (int) $fruitWeekPct ?>%;background:var(--color-mint)"></span></div>
            <div class="muted small" style="margin-top:8px">días con 3 · prom. <?= e(number_format((float) ($fruit['avg_week'] ?? 0), 1, ',', '.')) ?></div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Este mes</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($fruit['month_days_done'] ?? 0) ?>/<?= (int) ($fruit['month_days'] ?? 0) ?></div>
            <div class="progress"><span style="width:<?= (int) $fruitMonthPct ?>%;background:var(--color-yellow)"></span></div>
            <div class="muted small" style="margin-top:8px"><?= (int) $fruitMonthPct ?>% de los <?= (int) ($fruit['month_days'] ?? 0) ?> días (meta 3/día)</div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Racha</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($fruit['streak'] ?? 0) ?></div>
            <div class="muted small" style="margin-top:8px">días seguidos · <?= (int) ($fruit['year_days_done'] ?? 0) ?> en el año</div>
        </article>
    </div>
</section>

<section class="card" style="margin-top:16px">
    <div class="card-header">
        <h2>Amigos/as · interacción</h2>
        <a class="text-link" href="<?= e(url('/friends')) ?>">Ver lista →</a>
    </div>
    <div class="kpi-grid kpi-grid-4" style="margin:0">
        <article class="metrics-area-card">
            <header><strong>Cobertura del mes</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= $friendsCoverage ?>%</div>
            <div class="progress"><span style="width:<?= min(100, $friendsCoverage) ?>%;background:var(--color-lavender)"></span></div>
            <div class="muted small" style="margin-top:8px">
                <?= (int) ($friends['talked_month'] ?? 0) ?> de <?= $friendsTotal ?> este mes
            </div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Esta semana</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($friends['talked_week'] ?? 0) ?></div>
            <div class="muted small" style="margin-top:8px">
                personas · <?= (int) ($friends['talks_week'] ?? 0) ?> charlas · <?= (int) ($friends['days_week'] ?? 0) ?> días
            </div>
        </article>
        <article class="metrics-area-card">
            <header><strong>Racha</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($friends['streak'] ?? 0) ?></div>
            <div class="muted small" style="margin-top:8px">
                días seguidos con al menos una charla
                <?php if (!empty($friends['last_talk_date'])): ?>
                    · última <?= e(format_date((string) $friends['last_talk_date'], 'd M')) ?>
                <?php endif; ?>
            </div>
        </article>
        <article class="metrics-area-card">
            <header><strong>En el año</strong></header>
            <div class="kpi-value" style="font-size:1.4rem;margin:8px 0"><?= (int) ($friends['talks_year'] ?? 0) ?></div>
            <div class="muted small" style="margin-top:8px">
                charlas · <?= (int) ($friends['talked_year'] ?? 0) ?> personas · <?= (int) ($friends['never'] ?? 0) ?> sin hablar
                <?php if (!empty($friends['suggested']['name'])): ?>
                    · hoy: <?= e((string) $friends['suggested']['name']) ?>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>

<section class="card" style="margin-top:16px">
    <div class="card-header">
        <h2>Comparación de <?= (int) $areaCount ?> áreas · <?= (int) $year ?></h2>
        <span class="muted small">vs <?= (int) $compareYear ?></span>
    </div>
    <div class="metrics-area-grid">
        <?php if ($areaSnap === []): ?>
            <p class="muted">No hay áreas anuales en configuración.</p>
        <?php endif; ?>
        <?php foreach ($areaSnap as $a):
            $prev = $prevByKey[(string) $a['area_key']] ?? null;
            if ($prev === null && !empty($a['area_id'])) {
                $prev = $prevById[(int) $a['area_id']] ?? null;
            }
            $currentPct = (float) $a['avg_progress'];
            $prevPct = $prev !== null ? (float) $prev['avg_progress'] : null;
            $delta = $prevPct !== null ? ($currentPct - $prevPct) : null;
            ?>
            <article class="metrics-area-card">
                <header>
                    <span class="area-dot" style="background:<?= e((string) ($a['color'] ?? '#7C83E1')) ?>"></span>
                    <strong><?= e((string) $a['name']) ?></strong>
                </header>
                <div class="progress" style="margin:8px 0"><span style="width:<?= e((string) min(100, $currentPct)) ?>%;background:<?= e((string) ($a['color'] ?? '#7C83E1')) ?>"></span></div>
                <div class="metrics-area-stats">
                    <span><?= e(number_format($currentPct, 0)) ?>%</span>
                    <span class="muted"><?= (int) $a['completed'] ?>/<?= (int) $a['total'] ?> hechos</span>
                    <?php if ($prevPct !== null): ?>
                        <span class="muted metrics-area-vs">vs <?= (int) $compareYear ?>: <?= e(number_format($prevPct, 0)) ?>%</span>
                        <span class="<?= $delta >= 0 ? 'text-ok' : 'text-warn' ?>">
                            <?= $delta >= 0 ? '+' : '' ?><?= e(number_format((float) $delta, 0)) ?> pts
                        </span>
                    <?php else: ?>
                        <span class="muted">sin datos en <?= (int) $compareYear ?></span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="split-2" style="margin-top:16px">
    <article class="card">
        <div class="card-header"><h2>Hábitos · semana</h2></div>
        <div class="chart-box">
            <canvas id="metricsWeek" aria-label="Cumplimiento semanal"></canvas>
        </div>
        <script type="application/json" data-chart="metricsWeek"><?= json_encode($weeklyChart, JSON_UNESCAPED_UNICODE) ?></script>
    </article>
    <article class="card">
        <div class="card-header"><h2>Distribución de objetivos</h2></div>
        <div class="chart-box">
            <canvas id="metricsAreas" aria-label="Distribución por área"></canvas>
        </div>
        <script type="application/json" data-chart="metricsAreas"><?= json_encode($areaChart, JSON_UNESCAPED_UNICODE) ?></script>
    </article>
</section>

<section class="card" style="margin-top:16px">
    <div class="card-header"><h2>Tendencia de hábitos</h2></div>
    <div class="chart-box chart-box--sm">
        <canvas id="metricsHeat" aria-label="Tendencia de hábitos"></canvas>
    </div>
    <script type="application/json" data-chart="metricsHeat"><?= json_encode($heatmapChart, JSON_UNESCAPED_UNICODE) ?></script>
</section>

<section class="card" style="margin-top:16px">
    <div class="card-header">
        <h2>Catálogo de métricas</h2>
        <span class="muted small">Valores calculados · <?= (int) $year ?></span>
    </div>
    <div class="metric-catalog-grid">
        <?php $tone = 'primary'; ?>
        <?php foreach ($consolidatedMetrics as $metric): ?>
            <?php require __DIR__ . '/_tile.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php
    $areaTones = [
        'annual_educacion' => 'lavender',
        'annual_trabajo' => 'primary',
        'annual_marca' => 'peach',
        'annual_finanzas' => 'yellow',
        'annual_salud' => 'mint',
        'annual_proyectos' => 'lavender',
    ];
    foreach ($areaMetrics as $key => $block):
        $tone = $areaTones[$key] ?? 'primary';
        ?>
        <details class="metrics-catalog" open>
            <summary>
                <span class="area-dot" style="background:<?= e((string) ($block['color'] ?? '#7C83E1')) ?>"></span>
                <?= e((string) $block['name']) ?>
            </summary>
            <?php foreach (['year' => 'Año ' . $year, 'day' => 'Día a día'] as $scope => $scopeLabel): ?>
                <?php if (empty($block[$scope])) {
                    continue;
                } ?>
                <h3><?= e($scopeLabel) ?></h3>
                <div class="metric-catalog-grid metric-catalog-grid--compact">
                    <?php foreach ($block[$scope] as $metric): ?>
                        <?php require __DIR__ . '/_tile.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php
            $charts = $block['charts'] ?? null;
            $chartId = preg_replace('/[^a-z0-9]/i', '', (string) $key);
            ?>
            <?php if ($charts === null || empty($charts['has_data'])): ?>
                <p class="muted small area-charts-empty">Todavía no hay datos del área en <?= (int) $year ?> para graficar.</p>
            <?php else: ?>
                <h3>Gráficos</h3>
                <div class="area-charts">
                    <?php
                    $chartBlocks = [
                        ['key' => 'bar', 'title' => 'Actividad por mes', 'hint' => 'Meses tildados y hábitos cumplidos'],
                        ['key' => 'pie', 'title' => 'Composición de objetivos', 'hint' => 'Reparto por estado'],
                        ['key' => 'line', 'title' => 'Ritmo acumulado', 'hint' => 'Cómo se acumula el año'],
                    ];
                    foreach ($chartBlocks as $chartBlock):
                        $canvasId = 'areaChart' . ucfirst($chartBlock['key']) . $chartId;
                        ?>
                        <article class="area-chart-card">
                            <header>
                                <strong><?= e($chartBlock['title']) ?></strong>
                                <span class="muted small"><?= e($chartBlock['hint']) ?></span>
                            </header>
                            <div class="chart-box chart-box--sm">
                                <canvas id="<?= e($canvasId) ?>" aria-label="<?= e($chartBlock['title'] . ' de ' . $block['name']) ?>"></canvas>
                            </div>
                            <script type="application/json" data-chart="<?= e($canvasId) ?>"><?= json_encode($charts[$chartBlock['key']], JSON_UNESCAPED_UNICODE) ?></script>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>
