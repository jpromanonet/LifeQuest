<?php

declare(strict_types=1);

final class Auth
{
    public static function startSession(string $name): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $lifetime = (int) app_config('session_lifetime', 86400);
        if ($lifetime < 300) {
            $lifetime = 300;
        }
        $idle = (int) app_config('session_idle', $lifetime);
        if ($idle < 60) {
            $idle = $lifetime;
        }

        ini_set('session.gc_maxlifetime', (string) max($lifetime, $idle));

        $cookiePath = self::sessionCookiePath();
        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start([
            'cookie_lifetime' => $lifetime,
            'cookie_path' => $cookiePath,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $secure,
            'use_strict_mode' => true,
            'use_only_cookies' => true,
        ]);

        if (empty($_SESSION['_cookie_paths_cleaned'])) {
            self::expireStraySessionCookies($cookiePath, $secure);
            $_SESSION['_cookie_paths_cleaned'] = 1;
        }

        self::enforceIdleTimeout($idle, $lifetime);
    }

    public static function attempt(string $email, string $password): bool
    {
        if (!self::allowLoginAttempt()) {
            return false;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT id, user_key, name, email, password_hash, locale, timezone, theme, is_active
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !(int) $user['is_active']) {
            self::recordLoginFailure();
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::recordLoginFailure();
            return false;
        }

        self::clearLoginFailures();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'key' => $user['user_key'],
            'name' => $user['name'],
            'email' => $user['email'],
            'locale' => $user['locale'],
            'timezone' => $user['timezone'],
            'theme' => $user['theme'] ?: 'light',
        ];
        $_SESSION['_last_activity'] = time();
        self::refreshSessionCookie((int) app_config('session_lifetime', 86400));

        $upd = Database::pdo()->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id');
        $upd->execute(['id' => $user['id']]);

        // Cada login asegura hábitos de sistema (nuevos deploys / usuarios viejos).
        if (class_exists(HabitService::class)) {
            try {
                (new HabitService())->ensureSystemHabits((int) $user['id']);
            } catch (Throwable) {
                // no bloquear el login
            }
        }

        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $path = self::sessionCookiePath();
            self::expireSessionCookie($path, $secure);
            self::expireStraySessionCookies($path, $secure);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (function_exists('wants_json_request') && wants_json_request()) {
                json_response(['ok' => false, 'error' => 'Sesión expirada. Volvé a iniciar sesión.', 'redirect' => absolute_url('/login')], 401);
            }
            redirect('/login');
        }
    }

    public static function theme(): string
    {
        $theme = $_SESSION['user']['theme'] ?? 'light';
        return in_array($theme, ['light', 'dark', 'system'], true) ? $theme : 'light';
    }

    private static function enforceIdleTimeout(int $idleSeconds, int $cookieLifetime = 86400): void
    {
        if ($idleSeconds < 60 || !isset($_SESSION['user'])) {
            return;
        }
        $last = (int) ($_SESSION['_last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > $idleSeconds) {
            self::logout();
            return;
        }
        $_SESSION['_last_activity'] = time();
        self::refreshSessionCookie($cookieLifetime > 0 ? $cookieLifetime : $idleSeconds);
    }

    /** Path de la cookie = carpeta de la app (/lifequest), no / — evita dos cookies con el mismo nombre. */
    private static function sessionCookiePath(): string
    {
        $base = function_exists('base_path') ? base_path() : '';
        if ($base === '' || $base === '/') {
            return '/';
        }
        return $base;
    }

    private static function expireSessionCookie(string $path, bool $secure): void
    {
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $path,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function expireStraySessionCookies(string $canonicalPath, bool $secure): void
    {
        // Solo la cookie vieja en "/". No tocar "/lifequest/" — algunos clientes
        // la tratan igual que "/lifequest" y borran la sesión recién creada.
        if ($canonicalPath !== '/') {
            self::expireSessionCookie('/', $secure);
        }
    }

    /** Corre la expiración de la cookie 24 h (u otra vida) desde esta visita. */
    private static function refreshSessionCookie(int $lifetime): void
    {
        if ($lifetime < 300 || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $params = session_get_cookie_params();
        $path = $params['path'] !== '' ? $params['path'] : self::sessionCookiePath();
        $options = [
            'expires' => time() + $lifetime,
            'path' => $path,
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] !== '' ? $params['samesite'] : 'Lax',
        ];
        if (($params['domain'] ?? '') !== '') {
            $options['domain'] = $params['domain'];
        }
        setcookie(session_name(), session_id(), $options);
    }

    private static function allowLoginAttempt(): bool
    {
        $key = '_login_fails';
        $fails = $_SESSION[$key] ?? ['count' => 0, 'until' => 0];
        if (($fails['until'] ?? 0) > time()) {
            return false;
        }
        return true;
    }

    public static function isLoginLocked(): bool
    {
        $fails = $_SESSION['_login_fails'] ?? ['count' => 0, 'until' => 0];
        return ((int) ($fails['until'] ?? 0)) > time();
    }

    private static function recordLoginFailure(): void
    {
        $fails = $_SESSION['_login_fails'] ?? ['count' => 0, 'until' => 0];
        $fails['count'] = (int) $fails['count'] + 1;
        if ($fails['count'] >= 8) {
            $fails['until'] = time() + 300;
            $fails['count'] = 0;
        }
        $_SESSION['_login_fails'] = $fails;
    }

    private static function clearLoginFailures(): void
    {
        unset($_SESSION['_login_fails']);
    }
}
