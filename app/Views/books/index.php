<?php
/** @var int $year */
/** @var bool $allYears */
/** @var list<int> $availableYears */
/** @var list<array> $books */
/** @var array<int,string> $monthLabels */
/** @var array{finished:int,reading:int,planned:int,total:int,pages:int,pages_read:int} $stats */
/** @var int|null $target */
$allYears = !empty($allYears);
$availableYears = !empty($availableYears) ? $availableYears : [$year];
$stats = $stats ?? ['finished' => 0, 'reading' => 0, 'planned' => 0, 'total' => 0, 'pages' => 0, 'pages_read' => 0];
$pagesRead = (int) ($stats['pages_read'] ?? $stats['pages'] ?? 0);
$bookTarget = max(1, (int) ($target ?? 12));
$bookPercent = $allYears
    ? ($stats['total'] > 0 ? min(100, ($stats['finished'] / $stats['total']) * 100) : 0)
    : min(100, ($stats['finished'] / $bookTarget) * 100);
$modalYear = $allYears ? (int) now_local()->format('Y') : (int) $year;
$statusLabels = [
    'planned' => 'Planificado',
    'reading' => 'Leyendo',
    'finished' => 'Terminado',
];
?>
<section class="page-header with-actions">
    <div>
        <h1>Libros</h1>
        <p class="muted"><?= $allYears ? 'Todos los años · totales y páginas leídas' : 'Lectura del año · Goodreads, páginas y mes planificado' ?></p>
    </div>
    <div class="btn-row">
        <?php if (!$allYears): ?>
            <a class="btn btn-ghost" href="<?= e(url('/goals?year=' . (int) $year)) ?>">← Objetivos</a>
        <?php else: ?>
            <a class="btn btn-ghost" href="<?= e(url('/goals')) ?>">← Objetivos</a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" data-open-modal="bookModal">+ Libro</button>
    </div>
</section>

<form method="get" action="<?= e(form_action()) ?>" class="books-year-bar">
    <?= route_field('/books') ?>
    <label class="field books-year-field">
        <span>Período</span>
        <select name="year" onchange="this.form.submit()" aria-label="Elegir período">
            <option value="all" <?= $allYears ? 'selected' : '' ?>>Todos los años</option>
            <?php foreach ($availableYears as $y): ?>
                <option value="<?= (int) $y ?>" <?= !$allYears && (int) $y === (int) $year ? 'selected' : '' ?>><?= (int) $y ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<section class="kpi-grid kpi-grid-4">
    <article class="card kpi-card kpi-card--mint">
        <?php if ($allYears): ?>
            <div class="kpi-label">Terminados</div>
            <div class="kpi-value"><?= (int) $stats['finished'] ?>/<?= (int) $stats['total'] ?></div>
            <div class="progress kpi-bar"><span style="width:<?= e(number_format($bookPercent, 1, '.', '')) ?>%"></span></div>
            <div class="muted small">En todos los años</div>
        <?php else: ?>
            <div class="kpi-label">Meta <?= (int) $year ?></div>
            <div class="kpi-value"><?= (int) $stats['finished'] ?>/<?= (int) $bookTarget ?></div>
            <div class="progress kpi-bar"><span style="width:<?= e(number_format($bookPercent, 1, '.', '')) ?>%"></span></div>
            <form method="post" action="<?= e(form_action()) ?>" class="target-stepper" data-lq-save>
                <?= csrf_field() ?>
                <?= route_field('/books/target') ?>
                <input type="hidden" name="year" value="<?= (int) $year ?>">
                <button
                    type="submit"
                    name="step"
                    value="-1"
                    class="target-stepper-btn"
                    title="Bajar la meta a <?= (int) $bookTarget - 1 ?> libros"
                    aria-label="Bajar la meta"
                    <?= $bookTarget <= 1 ? 'disabled' : '' ?>
                >−</button>
                <label class="sr-only" for="bookTargetInput">Meta de libros del año</label>
                <input
                    type="number"
                    id="bookTargetInput"
                    name="target"
                    min="1"
                    max="999"
                    step="1"
                    value="<?= (int) $bookTarget ?>"
                    class="target-stepper-value"
                    aria-describedby="bookTargetHint"
                >
                <button
                    type="submit"
                    name="step"
                    value="1"
                    class="target-stepper-btn"
                    title="Subir la meta a <?= (int) $bookTarget + 1 ?> libros"
                    aria-label="Subir la meta"
                >+</button>
                <span class="target-stepper-unit" id="bookTargetHint">libros al año</span>
                <button type="submit" class="sr-only">Guardar meta</button>
            </form>
        <?php endif; ?>
    </article>
    <article class="card kpi-card kpi-card--primary">
        <div class="kpi-label">Leyendo</div>
        <div class="kpi-value"><?= (int) $stats['reading'] ?></div>
        <div class="muted small"><?= $allYears ? 'En curso · todos los años' : 'En curso' ?></div>
    </article>
    <article class="card kpi-card kpi-card--lavender">
        <div class="kpi-label">En la lista</div>
        <div class="kpi-value"><?= (int) $stats['total'] ?></div>
        <div class="muted small"><?= (int) $stats['planned'] ?> planeados · <?= (int) $stats['finished'] ?> terminados</div>
    </article>
    <article class="card kpi-card kpi-card--yellow">
        <div class="kpi-label">Páginas leídas</div>
        <div class="kpi-value"><?= e(number_format((float) $pagesRead, 0, ',', '.')) ?></div>
        <div class="muted small"><?= $allYears ? 'Suma de todos los libros' : 'Acumuladas en ' . (int) $year ?></div>
    </article>
</section>

<section class="card">
    <?php if ($books === []): ?>
        <p class="empty-state"><?= $allYears ? 'Todavía no cargaste libros.' : 'Todavía no cargaste libros para ' . (int) $year . '.' ?></p>
    <?php else: ?>
        <div class="archive-table">
            <?php foreach ($books as $book): ?>
                <div class="archive-row">
                    <div>
                        <strong><?= e((string) $book['title']) ?></strong>
                        <div class="muted small">
                            <?php if ($allYears): ?>
                                <span class="book-year-pill"><?= (int) ($book['year_num'] ?? 0) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($book['planned_month'])): ?>
                                <?= e($monthLabels[(int) $book['planned_month']] ?? '') ?> ·
                            <?php endif; ?>
                            <?= e($statusLabels[(string) ($book['status'] ?? '')] ?? (string) $book['status']) ?>
                            <?php if ($book['pages'] !== null || (int) ($book['pages_read'] ?? 0) > 0): ?>
                                · <?= (int) ($book['pages_read'] ?? 0) ?><?= $book['pages'] !== null ? '/' . (int) $book['pages'] : '' ?> pág.
                            <?php endif; ?>
                            <?php if (!empty($book['finished_at'])): ?>
                                · fin <?= e(format_date($book['finished_at'], 'd M Y')) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="btn-row">
                        <?php if (!empty($book['goodreads_url'])): ?>
                            <a class="btn btn-yellow btn-sm" href="<?= e((string) $book['goodreads_url']) ?>" target="_blank" rel="noopener">Goodreads</a>
                        <?php endif; ?>
                        <button
                            type="button"
                            class="btn btn-ghost btn-sm"
                            data-edit-book
                            data-id="<?= (int) $book['id'] ?>"
                            data-title="<?= e((string) $book['title']) ?>"
                            data-year="<?= e((string) ($book['year_num'] ?? $modalYear)) ?>"
                            data-planned-month="<?= e((string) ($book['planned_month'] ?? '')) ?>"
                            data-status="<?= e((string) ($book['status'] ?? 'planned')) ?>"
                            data-finished-at="<?= e((string) ($book['finished_at'] ?? '')) ?>"
                            data-pages="<?= e((string) ($book['pages'] ?? '')) ?>"
                            data-pages-read="<?= e((string) ($book['pages_read'] ?? '0')) ?>"
                            data-goodreads="<?= e((string) ($book['goodreads_url'] ?? '')) ?>"
                            data-notes="<?= e((string) ($book['notes'] ?? '')) ?>"
                        >Editar</button>
                        <form method="post" action="<?= e(form_action()) ?>" data-lq-save onsubmit="return confirm('¿Eliminar?');">
                            <?= csrf_field() ?>
                            <?= route_field('/books/' . (int) $book['id'] . '/delete') ?>
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<dialog class="modal" id="bookModal" data-default-year="<?= $modalYear ?>">
    <form method="post" id="bookForm" action="<?= e(form_action()) ?>" class="stack-form" data-lq-save>
        <?= csrf_field() ?>
        <input type="hidden" name="r" id="bookRoute" value="/books">
        <header class="modal-head">
            <h2 id="bookModalTitle">Nuevo libro</h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">×</button>
        </header>
        <label class="field"><span>Título</span><input type="text" name="title" id="bookTitle" required maxlength="255"></label>
        <div class="form-grid-2">
            <label class="field"><span>Año</span><input type="number" name="year" id="bookYear" value="<?= $modalYear ?>"></label>
            <label class="field">
                <span>Mes planificado</span>
                <select name="planned_month" id="bookPlannedMonth">
                    <option value="">Sin mes</option>
                    <?php foreach ($monthLabels as $n => $lab): ?>
                        <option value="<?= (int) $n ?>"><?= e($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Estado</span>
                <select name="status" id="bookStatus">
                    <option value="planned">No leído / planificado</option>
                    <option value="reading">Leyendo</option>
                    <option value="finished">Terminado</option>
                </select>
            </label>
            <label class="field"><span>Fecha de fin</span><input type="date" name="finished_at" id="bookFinishedAt"></label>
            <label class="field"><span>Páginas</span><input type="number" name="pages" id="bookPages" min="0"></label>
            <label class="field"><span>Páginas leídas</span><input type="number" name="pages_read" id="bookPagesRead" min="0" value="0"></label>
        </div>
        <label class="field"><span>Link Goodreads</span><input type="url" name="goodreads_url" id="bookGoodreads" placeholder="https://www.goodreads.com/..."></label>
        <label class="field"><span>Notas</span><textarea name="notes" id="bookNotes" rows="2"></textarea></label>
        <footer class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
            <button type="submit" class="btn btn-primary" id="bookSubmit">Guardar</button>
        </footer>
    </form>
</dialog>
