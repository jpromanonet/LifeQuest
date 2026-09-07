<?php
/**
 * Controles de frutas (banana / mandarina / naranja).
 *
 * @var array<string,mixed> $habit
 * @var string $todayDate
 * @var string $returnPath
 */
$qty = (float) ($habit['log_quantity'] ?? ($habit['log']['quantity'] ?? 0));
$target = max(1.0, (float) ($habit['target_per_period'] ?? 3));
$pct = min(100, (int) round(($qty / $target) * 100));
$returnPath = $returnPath ?? '/today';
$fruits = [
    ['key' => 'banana', 'label' => 'Banana'],
    ['key' => 'mandarina', 'label' => 'Mandarina'],
    ['key' => 'naranja', 'label' => 'Naranja'],
];
?>
<div class="habit-water habit-fruit" data-habit-qty data-habit-id="<?= (int) $habit['id'] ?>" onclick="event.stopPropagation()">
    <div class="habit-water-meta">
        <strong data-qty-value><?= e(number_format($qty, 0, ',', '.')) ?></strong>
        <span>/ <?= e(number_format($target, 0, ',', '.')) ?> frutas</span>
    </div>
    <div class="progress habit-water-bar habit-fruit-bar"><span data-qty-bar style="width:<?= $pct ?>%"></span></div>
    <div class="habit-water-btns habit-fruit-btns">
        <form method="post" action="<?= e(form_action()) ?>" class="habit-water-form" data-qty-delta>
            <?= csrf_field() ?>
            <?= route_field('/habits/' . (int) $habit['id'] . '/qty') ?>
            <input type="hidden" name="delta" value="-1">
            <input type="hidden" name="date" value="<?= e($todayDate) ?>">
            <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
            <button type="submit" class="habit-water-btn is-minus" title="Restar 1 fruta" aria-label="Restar 1 fruta">
                <span>−1</span>
            </button>
        </form>
        <?php foreach ($fruits as $fruit): ?>
            <form method="post" action="<?= e(form_action()) ?>" class="habit-water-form" data-qty-delta>
                <?= csrf_field() ?>
                <?= route_field('/habits/' . (int) $habit['id'] . '/qty') ?>
                <input type="hidden" name="delta" value="1">
                <input type="hidden" name="date" value="<?= e($todayDate) ?>">
                <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
                <button type="submit" class="habit-water-btn is-plus is-fruit is-<?= e($fruit['key']) ?>" title="Sumar <?= e($fruit['label']) ?>" aria-label="Sumar <?= e($fruit['label']) ?>">
                    <?php if ($fruit['key'] === 'banana'): ?>
                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6 7c2-3 6-4 9-2 3 2 4 6 3 10-1 3-4 5-7 5-2 0-3-1-3-3 0-3 2-5 5-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M15 5c1 .2 2.2.8 3 1.6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <?php elseif ($fruit['key'] === 'mandarina'): ?>
                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="12" cy="13" r="7" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M12 6c1-2 3-2.5 4-2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 13h4M12 11v4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="12" cy="13" r="7.2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M12 5.8c.8-1.6 2.4-2.2 3.6-2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 11.5c1.2 1.8 4.8 1.8 6 0" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                    <?php endif; ?>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
