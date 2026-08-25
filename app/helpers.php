<?php

declare(strict_types=1);

function app_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $appUrl = (string) app_config('url', '');
    if ($appUrl !== '') {
        $urlPath = parse_url($appUrl, PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '' && $urlPath !== '/') {
            $cached = rtrim($urlPath, '/');
            return $cached;
        }
        $cached = '';
        return $cached;
    }

    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '/' || $script === '\\' || $script === '.') {
        $cached = '';
        return $cached;
    }
    $cached = rtrim($script, '/');
    return $cached;
}

function url(string $path = '/'): string
{
    $extraQuery = [];
    $hash = '';
    if (str_contains($path, '#')) {
        [$path, $hash] = explode('#', $path, 2);
        $hash = '#' . $hash;
    }
    if (str_contains($path, '?')) {
        [$path, $qs] = explode('?', $path, 2);
        parse_str($qs, $extraQuery);
    }

    $path = '/' . ltrim($path, '/');
    if ($path === '//') {
        $path = '/';
    }

    $base = base_path();

    if (str_starts_with($path, '/assets/') || preg_match('#^/[^/]+\.php$#', $path) === 1) {
        $suffix = $extraQuery ? ('?' . http_build_query($extraQuery)) : '';
        return $base . $path . $suffix . $hash;
    }

    $script = $base . '/index.php';
    $params = $extraQuery;
    if ($path !== '/') {
        $params = array_merge(['r' => $path], $params);
    }
    $suffix = $params ? ('?' . http_build_query($params)) : '';
    return $script . $suffix . $hash;
}

/** Absolute URL for redirects (relative Location headers can fail behind some proxies). */
function absolute_url(string $path = '/'): string
{
    $relative = url($path);
    if (preg_match('#^https?://#i', $relative) === 1) {
        return $relative;
    }

    $appUrl = rtrim((string) app_config('url', ''), '/');
    if ($appUrl !== '') {
        $parts = parse_url($appUrl);
        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? 'localhost';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        // url() already includes base_path from APP_URL — only prepend scheme://host
        return $scheme . '://' . $host . $port . $relative;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host . $relative;
}

function redirect(string $path): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $target = absolute_url($path);
    if (!headers_sent()) {
        header('Location: ' . $target, true, 303);
    }

    // Fallback: even if Location was swallowed (headers already sent / proxy), force navigation.
    $safe = e($target);
    $json = json_encode($target, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . $safe . '">';
    echo '<script>location.replace(' . $json . ');</script>';
    echo '<title>Redirigiendo…</title></head><body>';
    echo '<p>Guardado. <a href="' . $safe . '">Continuar</a></p>';
    echo '</body></html>';
    exit;
}

function form_action(?string $route = null): string
{
    // Siempre el script; la ruta va en <input name="r"> (route_field), no en ?r= del action.
    return base_path() . '/index.php';
}

function route_field(string $path): string
{
    $path = '/' . ltrim($path, '/');
    if ($path === '/' || $path === '//') {
        return '';
    }
    return '<input type="hidden" name="r" value="' . e($path) . '">';
}

function view(string $template, array $data = [], ?string $layout = 'layouts/main'): void
{
    extract($data, EXTR_SKIP);
    $appName = (string) app_config('name', 'LifeQuest');
    $user = Auth::user();
    $templateFile = dirname(__DIR__) . '/app/Views/' . $template . '.php';
    if (!is_file($templateFile)) {
        throw new RuntimeException('View not found: ' . $template);
    }
    if ($layout === null) {
        require $templateFile;
        return;
    }
    $layoutFile = dirname(__DIR__) . '/app/Views/' . $layout . '.php';
    require $layoutFile;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        if (wants_json_request()) {
            json_response(['ok' => false, 'error' => 'Token CSRF inválido. Recargá la página e intentá de nuevo.'], 419);
        }
        http_response_code(419);
        exit('Token CSRF inválido');
    }
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($msg) ? $msg : null;
}

function wants_json_request(): bool
{
    // Solo XHR explícito. NO usar Accept: muchos browsers lo mandan y rompería el redirect de los forms.
    $xhr = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    return strcasecmp($xhr, 'XMLHttpRequest') === 0;
}

function respond_saved(string $message, string $path): never
{
    flash('success', $message);
    if (wants_json_request()) {
        json_response([
            'ok' => true,
            'message' => $message,
            'redirect' => absolute_url($path),
        ]);
    }
    redirect($path);
}

function respond_error(string $message, string $path, int $status = 422): never
{
    flash('error', $message);
    if (wants_json_request()) {
        json_response([
            'ok' => false,
            'error' => $message,
            'redirect' => absolute_url($path),
        ], $status);
    }
    redirect($path);
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function now_local(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone((string) app_config('timezone', 'America/Argentina/Buenos_Aires')));
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    if ($date === null || $date === '') {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($date);
        return $dt->format($format);
    } catch (Throwable) {
        return $date;
    }
}

function greeting_for_hour(int $hour): string
{
    if ($hour < 12) {
        return 'Buen día';
    }
    if ($hour < 19) {
        return 'Buenas tardes';
    }
    return 'Buenas noches';
}

function status_label(string $status): string
{
    return match ($status) {
        'idea' => 'Idea',
        'planned', 'planificado' => 'Planificado',
        'active', 'activo' => 'Activo',
        'paused', 'pausado' => 'Pausado',
        'completed', 'completado' => 'Completado',
        'cancelled', 'cancelado' => 'Cancelado',
        'archived', 'archivado' => 'Archivado',
        default => ucfirst($status),
    };
}

function priority_label(string $priority): string
{
    return match ($priority) {
        'low', 'baja' => 'Baja',
        'medium', 'media' => 'Media',
        'high', 'alta' => 'Alta',
        'critical', 'critica', 'crítica' => 'Crítica',
        default => ucfirst($priority),
    };
}

function habit_color_group(int $displayNumber): int
{
    if ($displayNumber < 1) {
        return 0;
    }
    return intdiv($displayNumber - 1, 3) % 5;
}

/** Color sólido del grupo pastel de hábitos (para charts). */
function habit_color_hex(int $displayNumber): string
{
    return match (habit_color_group($displayNumber)) {
        1 => '#78C6B0',
        2 => '#F4B8A8',
        3 => '#F6D58A',
        4 => '#C9B6E4',
        default => '#7C83E1',
    };
}

function first_name(string $fullName): string
{
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    return $parts[0] ?? $fullName;
}
