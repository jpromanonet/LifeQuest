<?php
/** @var array $profile */
/** @var list<array> $annualAreas */
/** @var list<array> $horizonAreas */

$areaGroups = [
    [
        'title' => 'Áreas del año',
        'hint' => 'Las seis áreas que estructuran cada plan anual',
        'areas' => $annualAreas,
        'tone' => 'primary',
        'newName' => 'new_annual_name',
        'newColor' => 'new_annual_color',
        'newLabel' => 'Nueva área anual',
        'defaultColor' => '#7C83E1',
    ],
    [
        'title' => 'Áreas de horizonte',
        'hint' => 'Los grandes objetivos de vida, sin año asignado',
        'areas' => $horizonAreas,
        'tone' => 'lavender',
        'newName' => 'new_horizon_name',
        'newColor' => 'new_horizon_color',
        'newLabel' => 'Nueva área de horizonte',
        'defaultColor' => '#4F46E5',
    ],
];
?>
<section class="page-header">
    <div>
        <h1>Configuración</h1>
        <p class="muted">Perfil, contraseña y áreas de trabajo</p>
    </div>
</section>

<div class="split-2">
    <section class="card">
        <div class="card-header"><h2>Perfil</h2></div>
        <form method="post" action="<?= e(form_action('/settings/profile')) ?>" class="stack-form">
            <?= csrf_field() ?>
            <?= route_field('/settings/profile') ?>
            <div class="form-grid-2">
                <label class="field"><span>Nombre</span><input type="text" name="name" value="<?= e((string) ($profile['name'] ?? '')) ?>" required></label>
                <label class="field"><span>Correo</span><input type="email" name="email" value="<?= e((string) ($profile['email'] ?? '')) ?>" required></label>
                <label class="field"><span>Idioma</span><input type="text" name="locale" value="<?= e((string) ($profile['locale'] ?? 'es_AR')) ?>"></label>
                <label class="field"><span>Zona horaria</span><input type="text" name="timezone" value="<?= e((string) ($profile['timezone'] ?? 'America/Argentina/Buenos_Aires')) ?>"></label>
            </div>
            <button type="submit" class="btn btn-primary">Guardar perfil</button>
        </form>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Contraseña</h2>
            <span class="muted small">Mínimo 8 caracteres</span>
        </div>
        <form method="post" action="<?= e(form_action('/settings/password')) ?>" class="stack-form">
            <?= csrf_field() ?>
            <?= route_field('/settings/password') ?>
            <label class="field">
                <span>Contraseña actual</span>
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <div class="form-grid-2">
                <label class="field">
                    <span>Nueva contraseña</span>
                    <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
                </label>
                <label class="field">
                    <span>Repetir nueva</span>
                    <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                </label>
            </div>
            <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
        </form>
    </section>
</div>

<form method="post" action="<?= e(form_action('/settings/areas')) ?>">
    <?= csrf_field() ?>
    <?= route_field('/settings/areas') ?>

    <?php foreach ($areaGroups as $group): ?>
        <section class="card area-card">
            <div class="card-header">
                <h2><?= e($group['title']) ?></h2>
                <span class="muted small"><?= count($group['areas']) ?> · <?= e($group['hint']) ?></span>
            </div>

            <div class="area-tiles">
                <?php foreach ($group['areas'] as $area):
                    $color = (string) ($area['color'] ?? $group['defaultColor']);
                    ?>
                    <div class="area-tile" style="--area-color:<?= e($color) ?>">
                        <label class="area-tile-swatch" title="Cambiar color">
                            <input type="color" name="area_color[<?= (int) $area['id'] ?>]" value="<?= e($color) ?>">
                        </label>
                        <input
                            class="area-tile-name"
                            type="text"
                            name="area_name[<?= (int) $area['id'] ?>]"
                            value="<?= e((string) $area['name']) ?>"
                            required
                            maxlength="120"
                            aria-label="Nombre del área"
                        >
                        <button
                            type="submit"
                            form="deleteArea<?= (int) $area['id'] ?>"
                            class="icon-btn area-tile-remove"
                            title="Eliminar área"
                            onclick="return confirm('¿Eliminar el área «<?= e((string) $area['name']) ?>»?');"
                        >×</button>
                    </div>
                <?php endforeach; ?>

                <div class="area-tile area-tile--new">
                    <label class="area-tile-swatch" title="Color de la nueva área">
                        <input type="color" name="<?= e($group['newColor']) ?>" value="<?= e($group['defaultColor']) ?>">
                    </label>
                    <input
                        class="area-tile-name"
                        type="text"
                        name="<?= e($group['newName']) ?>"
                        maxlength="120"
                        placeholder="<?= e($group['newLabel']) ?>"
                        aria-label="<?= e($group['newLabel']) ?>"
                    >
                </div>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="settings-actions">
        <button type="submit" class="btn btn-primary">Guardar áreas</button>
    </div>
</form>

<?php foreach (array_merge($annualAreas, $horizonAreas) as $area): ?>
    <form method="post" action="<?= e(form_action('/settings/areas/' . (int) $area['id'] . '/delete')) ?>" id="deleteArea<?= (int) $area['id'] ?>" hidden>
        <?= csrf_field() ?>
        <?= route_field('/settings/areas/' . (int) $area['id'] . '/delete') ?>
    </form>
<?php endforeach; ?>
