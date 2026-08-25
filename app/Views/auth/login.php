<?php
/** @var string|null $error */
/** @var string $appName */
?>
<div class="auth-split">
    <section class="auth-brand" aria-label="LifeQuest">
        <div class="auth-brand-inner">
            <div class="brand-mark brand-mark-lg" aria-hidden="true">
                <svg viewBox="0 0 48 64" width="56" height="72" fill="none">
                    <path d="M10 54 C16 42, 12 32, 20 24 C28 16, 26 10, 30 4" stroke="#AEB3F4" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="10" cy="54" r="5.5" fill="#78C6B0"/>
                    <circle cx="16" cy="38" r="5.5" fill="#F4B8A8"/>
                    <circle cx="22" cy="22" r="5.5" fill="#F6D58A"/>
                    <circle cx="30" cy="4" r="5.5" fill="#7C83E1"/>
                    <path d="M30 0 L35 3.5 L30 3.5 Z" fill="#7C83E1"/>
                </svg>
            </div>
            <h1 class="auth-brand-title"><?= e($appName) ?></h1>
            <p class="auth-brand-tagline">Objetivos, hábitos y progreso</p>
            <div class="auth-illustration" aria-hidden="true">
                <svg viewBox="0 0 320 180" width="100%" height="180" fill="none">
                    <path d="M20 140 C70 120, 90 90, 140 100 C190 110, 210 70, 260 80 C285 85, 295 60, 310 55" stroke="#D8DBF5" stroke-width="10" stroke-linecap="round"/>
                    <circle cx="40" cy="135" r="14" fill="#78C6B0"/>
                    <circle cx="120" cy="105" r="14" fill="#F4B8A8"/>
                    <circle cx="200" cy="90" r="14" fill="#F6D58A"/>
                    <circle cx="290" cy="58" r="16" fill="#7C83E1"/>
                    <path d="M290 42 L298 48 L290 48 Z" fill="#7C83E1"/>
                </svg>
                <p class="auth-illustration-caption">Dirección, constancia y progreso.</p>
            </div>
        </div>
    </section>

    <section class="auth-form-panel">
        <div class="auth-card">
            <h2>Iniciar sesión</h2>
            <?php if (!empty($error)): ?>
                <div class="flash flash-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= e(form_action()) ?>" class="stack-form">
                <?= csrf_field() ?>
                <?= route_field('/login') ?>
                <label class="field">
                    <span>Correo electrónico</span>
                    <span class="input-with-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <input type="email" name="email" required autocomplete="username" placeholder="tu@correo.com">
                    </span>
                </label>
                <label class="field">
                    <span>Contraseña</span>
                    <span class="input-with-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                    </span>
                </label>
                <div class="form-row-between">
                    <label class="checkbox">
                        <input type="checkbox" name="remember" value="1">
                        <span>Recordarme</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            </form>
        </div>
    </section>
</div>
