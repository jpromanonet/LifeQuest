<?php
/**
 * Tarjeta de métrica con barra de progreso opcional.
 *
 * @var array{label:string,value:string,hint?:string,percent?:float,tone?:string} $metric
 * @var string $tone Paleta pastel de la tarjeta.
 */
$metricTone = $tone ?? 'primary';
$metricPercent = isset($metric['percent']) ? max(0.0, min(100.0, (float) $metric['percent'])) : null;
$barTone = ($metric['tone'] ?? '') === 'warn' ? ' metric-tile-bar--warn' : '';
?>
<article class="metric-tile kpi-card--<?= e($metricTone) ?>">
    <div class="metric-tile-label"><?= e((string) $metric['label']) ?></div>
    <div class="metric-tile-value"><?= e((string) $metric['value']) ?></div>
    <?php if ($metricPercent !== null): ?>
        <div class="progress metric-tile-bar<?= $barTone ?>">
            <span style="width:<?= e(number_format($metricPercent, 1, '.', '')) ?>%"></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($metric['hint'])): ?>
        <div class="muted small metric-tile-hint"><?= e((string) $metric['hint']) ?></div>
    <?php endif; ?>
</article>
