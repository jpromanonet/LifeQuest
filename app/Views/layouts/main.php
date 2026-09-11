<?php
/** @var string $templateFile */
/** @var string $appName */
/** @var array|null $user */
/** @var string|null $currentNav */
/** @var string|null $title */

$themePref = Auth::theme();
$htmlTheme = $themePref === 'system' ? 'light' : $themePref;
$initials = 'LQ';
if ($user && !empty($user['name'])) {
    $parts = preg_split('/\s+/', trim((string) $user['name'])) ?: [];
    $initials = strtoupper(mb_substr($parts[0] ?? 'L', 0, 1) . mb_substr($parts[1] ?? 'Q', 0, 1));
}
$nav = $currentNav ?? '';
$success = flash('success');
$error = flash('error');
if (isset($flashSuccess) && is_string($flashSuccess) && $flashSuccess !== '') {
    $success = $flashSuccess;
}
if (isset($flashError) && is_string($flashError) && $flashError !== '') {
    $error = $flashError;
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= e($htmlTheme) ?>" data-theme-pref="<?= e($themePref) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(($title ?? 'LifeQuest') . ' · ' . $appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>?v=66">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <script src="<?= e(url('/assets/js/app.js')) ?>?v=66" defer></script>
</head>
<body class="app-body" data-app-index="<?= e(base_path() . '/index.php') ?>">
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Navegación principal">
        <div class="sidebar-brand">
            <div class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 40 48" width="36" height="44" fill="none">
                    <path d="M8 40 C12 32, 10 24, 16 18 C22 12, 20 8, 24 4" stroke="url(#pathGrad)" stroke-width="2.5" stroke-linecap="round"/>
                    <circle cx="8" cy="40" r="4" fill="#78C6B0"/>
                    <circle cx="14" cy="28" r="4" fill="#F4B8A8"/>
                    <circle cx="18" cy="16" r="4" fill="#F6D58A"/>
                    <circle cx="24" cy="4" r="4" fill="#7C83E1"/>
                    <path d="M24 0 L28 3 L24 3 Z" fill="#7C83E1"/>
                    <defs>
                        <linearGradient id="pathGrad" x1="8" y1="40" x2="24" y2="4">
                            <stop stop-color="#78C6B0"/><stop offset="1" stop-color="#7C83E1"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <div>
                <div class="brand-name"><?= e($appName) ?></div>
                <div class="brand-tagline">Objetivos, hábitos y progreso.</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <?php
            $items = [
                'today' => ['Hoy', '/today', 'home'],
                'goals' => ['Objetivos', '/goals', 'target'],
                'weekly' => ['Plan semanal', '/weekly', 'week'],
                'habits' => ['Hábitos', '/habits', 'check'],
                'milestones' => ['Hitos', '/milestones', 'milestone'],
                'friends' => ['Amigos/as', '/friends', 'friends'],
                'horizon' => ['Horizontes', '/horizon', 'horizon'],
                'rules' => ['Reglas propias', '/rules', 'rules'],
                'metrics' => ['Métricas', '/metrics', 'chart'],
                'reviews' => ['Revisiones', '/reviews', 'review'],
                'archive' => ['Archivo', '/archive', 'archive'],
                'settings' => ['Configuración', '/settings', 'settings'],
            ];
            require dirname(__DIR__) . '/partials/icons.php';
            foreach ($items as $key => [$label, $path, $icon]):
                $active = $nav === $key ? ' is-active' : '';
            ?>
                <a class="nav-item<?= $active ?>" href="<?= e(url($path)) ?>">
                    <span class="nav-icon"><?= icon_svg($icon) ?></span>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <svg class="sidebar-path" viewBox="0 0 200 80" fill="none" aria-hidden="true">
                <path d="M10 60 C40 50, 50 30, 80 35 C110 40, 120 20, 150 25 C170 28, 180 15, 190 12" stroke="var(--color-border)" stroke-width="2" stroke-linecap="round"/>
                <circle cx="20" cy="56" r="5" fill="var(--color-mint)"/>
                <circle cx="80" cy="35" r="5" fill="var(--color-peach)"/>
                <circle cx="150" cy="25" r="5" fill="var(--color-yellow)"/>
                <circle cx="190" cy="12" r="5" fill="var(--color-primary)"/>
            </svg>
            <form method="post" action="<?= e(form_action('/logout')) ?>" class="sidebar-logout">
                <?= csrf_field() ?>
                <?= route_field('/logout') ?>
                <button type="submit" class="btn btn-logout">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>

    <div class="main-column">
        <header class="topbar">
            <button type="button" class="icon-btn sidebar-toggle" id="sidebarToggle" aria-label="Abrir menú">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <div class="topbar-spacer"></div>
            <div class="topbar-user">
                <button
                    type="button"
                    class="icon-btn theme-toggle"
                    data-theme-toggle
                    data-csrf="<?= e(csrf_token()) ?>"
                    data-theme-url="<?= e(url('/settings/theme')) ?>"
                    aria-label="Cambiar tema"
                    title="Cambiar tema"
                >
                    <svg class="theme-icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    <svg class="theme-icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3a7 7 0 0 0 11.5 11.5z"/></svg>
                </button>
                <div class="avatar" title="<?= e((string) ($user['name'] ?? '')) ?>"><?= e($initials) ?></div>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="flash flash-success" role="status"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash flash-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <main class="page-content">
            <?php require $templateFile; ?>
        </main>
    </div>
</div>

<button type="button" class="scroll-top" id="scrollTopBtn" aria-label="Volver arriba" title="Volver arriba" hidden>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 19V5M5 12l7-7 7 7"/>
    </svg>
</button>
</body>
</html>
