<?php
/**
 * Controles de agua (vaso 250 / botella 500).
 *
 * @var array<string,mixed> $habit
 * @var string $todayDate
 * @var string $returnPath
 */
$qty = (float) ($habit['log_quantity'] ?? ($habit['log']['quantity'] ?? 0));
$target = max(1.0, (float) ($habit['target_per_period'] ?? 2000));
$pct = min(100, (int) round(($qty / $target) * 100));
$done = $qty + 0.0001 >= $target;
$returnPath = $returnPath ?? '/today';
?>
<div class="habit-water" data-habit-qty data-habit-id="<?= (int) $habit['id'] ?>" onclick="event.stopPropagation()">
    <div class="habit-water-meta">
        <strong data-qty-value><?= e(number_format($qty, 0, ',', '.')) ?></strong>
        <span>/ <?= e(number_format($target, 0, ',', '.')) ?> ml</span>
    </div>
    <div class="progress habit-water-bar"><span data-qty-bar style="width:<?= $pct ?>%"></span></div>
    <div class="habit-water-btns">
        <?php foreach ([
            ['delta' => -500, 'title' => 'Restar botella 500 ml', 'kind' => 'bottle minus'],
            ['delta' => -250, 'title' => 'Restar vaso 250 ml', 'kind' => 'glass minus'],
            ['delta' => 250, 'title' => 'Sumar vaso 250 ml', 'kind' => 'glass plus'],
            ['delta' => 500, 'title' => 'Sumar botella 500 ml', 'kind' => 'bottle plus'],
        ] as $btn): ?>
            <form method="post" action="<?= e(form_action()) ?>" class="habit-water-form" data-qty-delta>
                <?= csrf_field() ?>
                <?= route_field('/habits/' . (int) $habit['id'] . '/qty') ?>
                <input type="hidden" name="delta" value="<?= (int) $btn['delta'] ?>">
                <input type="hidden" name="date" value="<?= e($todayDate) ?>">
                <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
                <button type="submit" class="habit-water-btn is-<?= e(explode(' ', $btn['kind'])[0]) ?> <?= str_contains($btn['kind'], 'minus') ? 'is-minus' : 'is-plus' ?>" title="<?= e($btn['title']) ?>" aria-label="<?= e($btn['title']) ?>">
                    <?php if (str_starts_with($btn['kind'], 'glass')): ?>
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M7 3h10l-1.2 16.2A2 2 0 0 1 13.8 21h-3.6a2 2 0 0 1-2-1.8L7 3Z" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M8 10h8" stroke="currentColor" stroke-width="1.5"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M8 4h8v14a3 3 0 0 1-3 3h-2a3 3 0 0 1-3-3V4Z" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M8 9h8M10 4V2h4v2" stroke="currentColor" stroke-width="1.6"/></svg>
                    <?php endif; ?>
                    <span><?= str_contains($btn['kind'], 'minus') ? '−' : '+' ?><?= str_starts_with($btn['kind'], 'glass') ? '250' : '500' ?></span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
