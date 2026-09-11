<?php
/** @var list<array{category:array,rules:list<array>}> $grouped */
/** @var int $activeCat */
/** @var int $totalRules */
/** @var int $categoryCount */
/** @var array{name:string,count:int}|null $maxCategory */

$avgRules = $categoryCount > 0 ? (int) round($totalRules / $categoryCount) : 0;
$activeCat = $activeCat ?? 0;
$currentPath = '/rules' . ($activeCat > 0 ? '?cat=' . $activeCat : '');
?>
<section class="page-header with-actions">
    <div>
        <h1>Reglas propias</h1>
        <p class="muted">Tus principios y reglas personales, por categoría · sin fechas ni tildes</p>
    </div>
    <button type="button" class="btn btn-primary" data-open-modal="ruleCategoryModal">+ Nueva categoría</button>
</section>

<section class="kpi-grid kpi-grid-3">
    <article class="card kpi-card kpi-card--primary">
        <div class="kpi-label">Reglas totales</div>
        <div class="kpi-value"><?= (int) $totalRules ?></div>
        <div class="muted small">En todas las categorías</div>
    </article>
    <article class="card kpi-card kpi-card--mint">
        <div class="kpi-label">Categorías</div>
        <div class="kpi-value"><?= (int) $categoryCount ?></div>
        <div class="muted small">Promedio: <?= (int) $avgRules ?> reglas por categoría</div>
    </article>
    <article class="card kpi-card kpi-card--lavender">
        <div class="kpi-label">Categoría con más reglas</div>
        <?php if ($maxCategory !== null && $maxCategory['count'] > 0): ?>
            <div class="kpi-value"><?= (int) $maxCategory['count'] ?></div>
            <div class="muted small"><?= e($maxCategory['name']) ?></div>
        <?php else: ?>
            <div class="kpi-value">—</div>
            <div class="muted small">Todavía sin reglas</div>
        <?php endif; ?>
    </article>
</section>

<?php if ($grouped !== []): ?>
    <form method="get" action="<?= e(form_action()) ?>" class="filters-bar rules-filter">
        <?= route_field('/rules') ?>
        <label>
            <span class="sr-only">Categoría</span>
            <select name="cat" onchange="this.form.submit()" aria-label="Filtrar por categoría">
                <option value="">Todas las categorías · <?= (int) $totalRules ?> reglas</option>
                <?php foreach ($grouped as $section):
                    $cat = $section['category'];
                    $cid = (int) $cat['id'];
                    ?>
                    <option value="<?= $cid ?>" <?= $activeCat === $cid ? 'selected' : '' ?>>
                        <?= e((string) $cat['name']) ?> · <?= count($section['rules']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($activeCat > 0): ?>
            <a class="btn btn-ghost" href="<?= e(url('/rules')) ?>">Limpiar</a>
        <?php endif; ?>
    </form>
<?php endif; ?>

<div class="goals-main">
    <?php if ($grouped === []): ?>
        <div class="card empty-state">
            Todavía no hay categorías. Creá la primera con “+ Nueva categoría”.
        </div>
    <?php endif; ?>

    <?php foreach (array_values($grouped) as $catIndex => $section):
        $category = $section['category'];
        $rules = $section['rules'];
        $catId = (int) $category['id'];
        $colorGroup = $catIndex % 5;
        if ($activeCat > 0 && $catId !== $activeCat) {
            continue;
        }
        ?>
        <section class="card goal-section rules-section" data-color-group="<?= $colorGroup ?>">
            <header class="section-head rules-head">
                <span class="rules-cat-badge" aria-hidden="true"><?= mb_strtoupper(mb_substr((string) $category['name'], 0, 1)) ?></span>
                <h2><?= e((string) $category['name']) ?></h2>
                <span class="rules-count-pill"><?= count($rules) ?> regla<?= count($rules) === 1 ? '' : 's' ?></span>
                <div class="btn-row rules-cat-actions">
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm"
                        data-edit-rule-category
                        data-id="<?= $catId ?>"
                        data-name="<?= e((string) $category['name']) ?>"
                    >Renombrar</button>
                    <form method="post" action="<?= e(form_action()) ?>" data-lq-save onsubmit="return confirm('¿Eliminar la categoría y todas sus reglas?');">
                        <?= csrf_field() ?>
                        <?= route_field('/rules/categories/' . $catId . '/delete') ?>
                        <input type="hidden" name="redirect" value="/rules">
                        <button type="submit" class="btn btn-danger btn-sm">×</button>
                    </form>
                </div>
            </header>

            <div class="rules-body">
                <?php if ($rules === []): ?>
                    <p class="rules-empty">Sin reglas todavía. Escribí la primera acá abajo.</p>
                <?php else: ?>
                    <ol class="rules-list">
                        <?php foreach ($rules as $index => $rule): ?>
                            <li class="rules-item">
                                <span class="habit-num rule-num"><?= (int) $index + 1 ?></span>
                                <span class="rules-item-text">
                                    <span class="rules-item-title"><?= e((string) $rule['title']) ?></span>
                                    <?php if (!empty($rule['body'])): ?>
                                        <span class="muted small"><?= e((string) $rule['body']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <div class="btn-row rules-item-actions">
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-sm"
                                        data-edit-rule
                                        data-id="<?= (int) $rule['id'] ?>"
                                        data-title="<?= e((string) $rule['title']) ?>"
                                        data-body="<?= e((string) ($rule['body'] ?? '')) ?>"
                                    >Editar</button>
                                    <form method="post" action="<?= e(form_action()) ?>" data-lq-save onsubmit="return confirm('¿Eliminar esta regla?');">
                                        <?= csrf_field() ?>
                                        <?= route_field('/rules/' . (int) $rule['id'] . '/delete') ?>
                                        <input type="hidden" name="redirect" value="<?= e($currentPath) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">×</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <form method="post" action="<?= e(form_action()) ?>" class="weekly-add-form rules-add-form" data-lq-save>
                    <?= csrf_field() ?>
                    <?= route_field('/rules') ?>
                    <input type="hidden" name="redirect" value="<?= e($currentPath) ?>">
                    <input type="hidden" name="category_id" value="<?= $catId ?>">
                    <div class="weekly-add-main">
                        <input type="text" name="title" required maxlength="255" placeholder="Nueva regla…" aria-label="Nueva regla para <?= e((string) $category['name']) ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Agregar</button>
                    </div>
                </form>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<dialog class="modal" id="ruleCategoryModal">
    <form method="post" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save>
        <?= csrf_field() ?>
        <?= route_field('/rules/categories') ?>
        <input type="hidden" name="redirect" value="/rules">
        <header class="modal-head">
            <h2>Nueva categoría</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Nombre</span>
            <input type="text" name="name" required maxlength="160" placeholder="Ej. Finanzas personales">
        </label>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear</button>
        </footer>
    </form>
</dialog>

<dialog class="modal" id="ruleCategoryEditModal">
    <form method="post" id="ruleCategoryEditForm" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="ruleCategoryEditRoute" value="/rules/categories/0">
        <input type="hidden" name="redirect" value="<?= e($currentPath) ?>">
        <header class="modal-head">
            <h2>Renombrar categoría</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Nombre</span>
            <input type="text" name="name" id="ruleCategoryEditName" required maxlength="160">
        </label>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </footer>
    </form>
</dialog>

<dialog class="modal" id="ruleEditModal">
    <form method="post" id="ruleEditForm" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="ruleEditRoute" value="/rules/0">
        <input type="hidden" name="redirect" value="<?= e($currentPath) ?>">
        <header class="modal-head">
            <h2>Editar regla</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field">
            <span>Regla</span>
            <input type="text" name="title" id="ruleEditTitle" required maxlength="255">
        </label>
        <label class="field">
            <span>Detalle (opcional)</span>
            <textarea name="body" id="ruleEditBody" rows="2"></textarea>
        </label>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </footer>
    </form>
</dialog>
