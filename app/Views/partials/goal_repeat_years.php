<?php
/**
 * Años futuros ya cargados para copiar un objetivo anual.
 *
 * @var int $year año del formulario (no se puede elegir)
 * @var list<int> $repeatYearOptions
 */
$repeatYearOptions = $repeatYearOptions ?? [];
?>
<?php if ($repeatYearOptions !== []): ?>
<fieldset class="year-repeat-box">
    <legend>También en</legend>
    <div class="year-repeat-list">
        <?php foreach ($repeatYearOptions as $y): ?>
            <label class="year-repeat-item">
                <input type="checkbox" name="repeat_years[]" value="<?= (int) $y ?>">
                <span><?= (int) $y ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <p class="muted small year-repeat-hint">Tildá los años y al guardar se crea una copia vacía en cada uno.</p>
</fieldset>
<?php endif; ?>
