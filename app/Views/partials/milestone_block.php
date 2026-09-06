<?php
/**
 * Modal bloqueante de hitos pendientes (hoy o vencidos).
 *
 * @var list<array<string,mixed>> $dueMilestones
 * @var string $todayDate
 */
$dueMilestones = $dueMilestones ?? [];
if ($dueMilestones === []) {
    return;
}
$current = $dueMilestones[0];
$remaining = count($dueMilestones);
$date = (string) ($current['milestone_date'] ?? $todayDate);
$isToday = $date === ($todayDate ?? '');
try {
    $dt = new DateTimeImmutable($date, now_local()->getTimezone());
    $months = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
    $dateLabel = $dt->format('j') . ' de ' . $months[(int) $dt->format('n')];
} catch (Throwable) {
    $dateLabel = $date;
}
?>
<dialog class="modal milestone-block-modal" id="milestoneBlockModal" data-milestone-block>
    <form method="post" action="<?= e(form_action()) ?>" class="milestone-block-form" data-lq-save>
        <?= csrf_field() ?>
        <?= route_field('/milestones/' . (int) $current['id'] . '/complete') ?>
        <input type="hidden" name="redirect" value="/today">
        <div class="milestone-block-inner">
            <p class="milestone-block-kicker"><?= $isToday ? 'Hito de hoy' : 'Hito pendiente' ?></p>
            <h2 class="milestone-block-title"><?= e((string) $current['title']) ?></h2>
            <p class="milestone-block-date"><?= e($dateLabel) ?></p>
            <?php if (!empty($current['notes'])): ?>
                <p class="milestone-block-notes"><?= e((string) $current['notes']) ?></p>
            <?php endif; ?>
            <?php if ($remaining > 1): ?>
                <p class="milestone-block-more muted small"><?= $remaining - 1 ?> hito<?= $remaining - 1 === 1 ? '' : 's' ?> más después de este</p>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary milestone-block-cta">Marcar como hecho</button>
        </div>
    </form>
</dialog>
