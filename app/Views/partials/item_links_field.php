<?php
/**
 * Campo repetible de links (name="links[]").
 *
 * @var list<string> $itemLinks
 * @var string $linksFieldId
 */
$itemLinks = $itemLinks ?? [];
$linksFieldId = $linksFieldId ?? 'itemLinks';
?>
<div class="field item-links-field" data-item-links id="<?= e($linksFieldId) ?>">
    <div class="item-links-head">
        <span>Links</span>
        <button type="button" class="btn btn-ghost btn-sm" data-add-link>+ Agregar link</button>
    </div>
    <div class="item-links-list" data-links-list>
        <?php foreach ($itemLinks as $url): ?>
            <div class="item-link-row">
                <input type="url" name="links[]" value="<?= e($url) ?>" placeholder="https://…" maxlength="500" inputmode="url">
                <button type="button" class="icon-btn" data-remove-link aria-label="Quitar link">×</button>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-link-row-template>
        <div class="item-link-row">
            <input type="url" name="links[]" value="" placeholder="https://…" maxlength="500" inputmode="url">
            <button type="button" class="icon-btn" data-remove-link aria-label="Quitar link">×</button>
        </div>
    </template>
    <p class="muted small">Podés sumar varios. En la lista, el ícono de link los abre en una pestaña nueva.</p>
</div>
