<?php
/**
 * Botón/enlace para abrir links guardados.
 *
 * @var list<string> $itemLinks
 */
$itemLinks = $itemLinks ?? [];
if ($itemLinks === []) {
    return;
}
$linkIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
?>
<?php if (count($itemLinks) === 1): ?>
    <a
        class="icon-btn item-link-btn"
        href="<?= e($itemLinks[0]) ?>"
        target="_blank"
        rel="noopener noreferrer"
        title="Abrir link"
        aria-label="Abrir link"
        onclick="event.stopPropagation()"
    ><?= $linkIcon ?></a>
<?php else: ?>
    <div class="item-link-menu">
        <button
            type="button"
            class="icon-btn item-link-btn"
            data-item-links-menu
            aria-haspopup="true"
            aria-expanded="false"
            title="Abrir links"
            aria-label="Abrir links"
        ><?= $linkIcon ?></button>
        <div class="item-link-popover" hidden role="menu">
            <?php foreach ($itemLinks as $index => $url): ?>
                <a
                    role="menuitem"
                    href="<?= e($url) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="<?= e($url) ?>"
                >Link <?= (int) $index + 1 ?></a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
