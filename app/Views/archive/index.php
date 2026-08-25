<?php
/** @var array<int, list<array>> $grouped */
/** @var list<int> $availableYears */
/** @var int|null $filterYear */

// Paleta pastel del manual, rotando por año para que cada bloque tenga identidad.
$archivePalette = [
    ['#7C83E1', '#AEB3F4'],
    ['#78C6B0', '#A7DECD'],
    ['#F4B8A8', '#F8D2C6'],
    ['#F6D58A', '#FCE7B4'],
    ['#C9B6E4', '#E0D3F1'],
];
$archiveIndex = 0;
?>
<section class="page-header with-actions">
    <div>
        <h1>Archivo</h1>
        <p class="muted">Objetivos históricos por año</p>
    </div>
    <form method="get" action="<?= e(form_action()) ?>" class="archive-year-form">
        <?= route_field('/archive') ?>
        <label class="field year-select-field">
            <span>Año</span>
            <select name="year" onchange="this.form.submit()" aria-label="Filtrar por año">
                <option value="" <?= $filterYear === null ? 'selected' : '' ?>>Todos</option>
                <?php foreach ($availableYears as $y): ?>
                    <?php if ((int) $y === 0) {
                        continue;
                    } ?>
                    <option value="<?= (int) $y ?>" <?= $filterYear === (int) $y ? 'selected' : '' ?>><?= (int) $y ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</section>

<?php if ($grouped === []): ?>
    <div class="card empty-state">No hay objetivos archivados o de años anteriores.</div>
<?php endif; ?>

<?php foreach ($grouped as $year => $goals):
    [$archiveFrom, $archiveTo] = $archivePalette[$archiveIndex % count($archivePalette)];
    $archiveIndex++;
    ?>
    <section class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h2 class="archive-year" style="--archive-from:<?= e($archiveFrom) ?>;--archive-to:<?= e($archiveTo) ?>">
                <?= (int) $year > 0 ? (int) $year : 'Sin año' ?>
            </h2>
            <span class="muted small"><?= count($goals) ?> objetivos</span>
        </div>
        <div class="archive-table" data-sortable>
            <div class="archive-table-head" role="row">
                <button type="button" class="sort-btn" data-sort="title" aria-label="Ordenar por objetivo">Objetivo</button>
                <button type="button" class="sort-btn" data-sort="status" aria-label="Ordenar por estado">Estado</button>
            </div>
            <?php foreach ($goals as $goal): ?>
                <div
                    class="archive-row"
                    data-sort-title="<?= e(mb_strtolower((string) $goal['title'])) ?>"
                    data-sort-status="<?= e((string) $goal['status']) ?>"
                    data-sort-progress="<?= e((string) (float) $goal['progress_percent']) ?>"
                >
                    <div>
                        <strong><?= e((string) $goal['title']) ?></strong>
                        <div class="muted small"><?= e((string) ($goal['area_name'] ?? 'Sin área')) ?></div>
                    </div>
                    <div class="archive-row-meta">
                        <span class="badge status-<?= e((string) $goal['status']) ?>"><?= e(status_label((string) $goal['status'])) ?></span>
                        <span class="muted small"><?= e(number_format((float) $goal['progress_percent'], 0)) ?>%</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
