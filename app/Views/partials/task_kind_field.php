<?php
$selectedKind = ($selectedKind ?? 'work') === 'personal' ? 'personal' : 'work';
?>
<fieldset class="task-kind-picker">
    <legend>Tipo</legend>
    <label class="task-kind-option">
        <input type="radio" name="task_kind" value="work" <?= $selectedKind === 'work' ? 'checked' : '' ?>>
        <span>Laborales</span>
    </label>
    <label class="task-kind-option">
        <input type="radio" name="task_kind" value="personal" <?= $selectedKind === 'personal' ? 'checked' : '' ?>>
        <span>Personales</span>
    </label>
</fieldset>
