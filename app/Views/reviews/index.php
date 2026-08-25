<?php
/** @var list<array> $reviews */
/** @var string $weekStart */
/** @var string $weekEnd */
/** @var string $monthStart */
/** @var string $monthEnd */

$typeLabels = [
    'weekly' => 'Semanal',
    'monthly' => 'Mensual',
    'quarterly' => 'Trimestral',
    'annual' => 'Anual',
];
?>
<section class="page-header">
    <div>
        <h1>Revisiones</h1>
        <p class="muted">Reflexión semanal y mensual para ajustar el rumbo</p>
    </div>
</section>

<section class="split-2">
    <article class="card">
        <div class="card-header"><h2>Nueva revisión semanal</h2></div>
        <form method="post" action="<?= e(url('/reviews')) ?>" class="stack-form">
            <?= csrf_field() ?>
            <input type="hidden" name="review_type" value="weekly">
            <input type="hidden" name="status" value="completed">
            <div class="form-grid-2">
                <label class="field"><span>Inicio</span><input type="date" name="period_start" value="<?= e($weekStart) ?>" required></label>
                <label class="field"><span>Fin</span><input type="date" name="period_end" value="<?= e($weekEnd) ?>" required></label>
            </div>
            <label class="field"><span>¿Qué salió bien?</span><textarea name="went_well" rows="3" required></textarea></label>
            <label class="field"><span>¿Qué mejorar?</span><textarea name="improve" rows="3"></textarea></label>
            <label class="field"><span>Foco de la próxima semana</span><textarea name="focus_next" rows="2"></textarea></label>
            <label class="field">
                <span>Energía (1–5)</span>
                <input type="number" name="energy" min="1" max="5" value="3">
            </label>
            <button type="submit" class="btn btn-primary">Guardar revisión semanal</button>
        </form>
    </article>

    <article class="card">
        <div class="card-header"><h2>Nueva revisión mensual</h2></div>
        <form method="post" action="<?= e(url('/reviews')) ?>" class="stack-form">
            <?= csrf_field() ?>
            <input type="hidden" name="review_type" value="monthly">
            <input type="hidden" name="status" value="completed">
            <div class="form-grid-2">
                <label class="field"><span>Inicio</span><input type="date" name="period_start" value="<?= e($monthStart) ?>" required></label>
                <label class="field"><span>Fin</span><input type="date" name="period_end" value="<?= e($monthEnd) ?>" required></label>
            </div>
            <label class="field"><span>Logros del mes</span><textarea name="went_well" rows="3" required></textarea></label>
            <label class="field"><span>Desafíos</span><textarea name="improve" rows="3"></textarea></label>
            <label class="field"><span>Prioridades del próximo mes</span><textarea name="focus_next" rows="2"></textarea></label>
            <label class="field"><span>Notas</span><textarea name="notes" rows="2"></textarea></label>
            <button type="submit" class="btn btn-primary">Guardar revisión mensual</button>
        </form>
    </article>
</section>

<section class="card" style="margin-top:16px">
    <div class="card-header"><h2>Historial</h2></div>
    <?php if ($reviews === []): ?>
        <p class="empty-state">Todavía no hay revisiones guardadas.</p>
    <?php else: ?>
        <ul class="review-history">
            <?php foreach ($reviews as $review): ?>
                <li>
                    <div>
                        <strong><?= e($typeLabels[$review['review_type']] ?? (string) $review['review_type']) ?></strong>
                        <span class="muted small">
                            <?= e(format_date($review['period_start'], 'd M Y')) ?>
                            —
                            <?= e(format_date($review['period_end'], 'd M Y')) ?>
                        </span>
                    </div>
                    <span class="badge"><?= e((string) $review['status']) ?></span>
                    <?php if (!empty($review['answers']['went_well'])): ?>
                        <p class="small"><?= e((string) $review['answers']['went_well']) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
