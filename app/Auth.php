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
        $lifetime = (int) app_config('session_lifetime', 28800);
        if ($lifetime < 300) {
            $lifetime = 300;
        }

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start([
            'cookie_lifetime' => $lifetime,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $secure,
            'use_strict_mode' => true,
            'use_only_cookies' => true,
        ]);

        self::enforceIdleTimeout((int) app_config('session_idle', 7200));
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

        $upd = Database::pdo()->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id');
        $upd->execute(['id' => $user['id']]);

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
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }
        session_destroy();
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

    private static function enforceIdleTimeout(int $idleSeconds): void
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
