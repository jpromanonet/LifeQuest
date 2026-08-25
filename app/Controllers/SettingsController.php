<?php

declare(strict_types=1);

final class SettingsController
{
    private AuditService $audit;
    private AreaService $areas;

    public function __construct()
    {
        $this->audit = new AuditService();
        $this->areas = new AreaService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();

        $stmt = Database::pdo()->prepare(
            'SELECT id, user_key, name, email, locale, timezone, theme
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $profile = $stmt->fetch() ?: Auth::user();

        view('settings/index', [
            'title' => 'Configuración',
            'currentNav' => 'settings',
            'profile' => $profile,
            'annualAreas' => $this->areas->listByScope($userId, 'annual'),
            'horizonAreas' => $this->areas->listByScope($userId, 'horizon'),
            'theme' => Auth::theme(),
            'flashSuccess' => flash('success'),
            'flashError' => flash('error'),
        ]);
    }

    public function updateProfile(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();

        $name = trim((string) input('name', ''));
        $email = trim((string) input('email', ''));
        $locale = trim((string) input('locale', 'es_AR'));
        $timezone = trim((string) input('timezone', 'America/Argentina/Buenos_Aires'));

        if ($name === '' || $email === '') {
            flash('error', 'Nombre y correo son obligatorios.');
            redirect('/settings');
        }

        try {
            $stmt = Database::pdo()->prepare(
                'UPDATE users
                 SET name = :name, email = :email, locale = :locale, timezone = :timezone
                 WHERE id = :id'
            );
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'locale' => $locale !== '' ? $locale : 'es_AR',
                'timezone' => $timezone !== '' ? $timezone : 'America/Argentina/Buenos_Aires',
                'id' => $userId,
            ]);

            if (isset($_SESSION['user'])) {
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['locale'] = $locale;
                $_SESSION['user']['timezone'] = $timezone;
            }

            $this->audit->log($userId, 'settings.profile', 'user', $userId);
            flash('success', 'Perfil actualizado.');
        } catch (Throwable $e) {
            flash('error', 'No se pudo actualizar el perfil.');
        }

        redirect('/settings');
    }

    public function updatePassword(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();

        $current = (string) input('current_password', '');
        $new = (string) input('new_password', '');
        $confirm = (string) input('confirm_password', '');

        if ($new !== $confirm) {
            flash('error', 'La nueva contraseña y su confirmación no coinciden.');
            redirect('/settings');
        }
        if (mb_strlen($new) < 8) {
            flash('error', 'La nueva contraseña debe tener al menos 8 caracteres.');
            redirect('/settings');
        }

        try {
            $stmt = Database::pdo()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $hash = (string) ($stmt->fetchColumn() ?: '');

            if ($hash === '' || !password_verify($current, $hash)) {
                flash('error', 'La contraseña actual no es correcta.');
                redirect('/settings');
            }

            Database::pdo()
                ->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                ->execute([
                    'hash' => password_hash($new, PASSWORD_DEFAULT),
                    'id' => $userId,
                ]);

            $this->audit->log($userId, 'settings.password', 'user', $userId);
            flash('success', 'Contraseña actualizada.');
        } catch (Throwable $e) {
            flash('error', 'No se pudo actualizar la contraseña.');
        }

        redirect('/settings');
    }

    public function updateTheme(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $theme = (string) input('theme', 'light');
        if (!in_array($theme, ['light', 'dark', 'system'], true)) {
            $theme = 'light';
        }

        try {
            $stmt = Database::pdo()->prepare('UPDATE users SET theme = :theme WHERE id = :id');
            $stmt->execute(['theme' => $theme, 'id' => $userId]);
            if (isset($_SESSION['user'])) {
                $_SESSION['user']['theme'] = $theme;
            }
            $this->audit->log($userId, 'settings.theme', 'user', $userId, ['theme' => $theme]);
            if ($this->wantsJson()) {
                json_response(['ok' => true, 'theme' => $theme]);
            }
            flash('success', 'Tema actualizado.');
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                json_response(['ok' => false], 422);
            }
            flash('error', 'No se pudo actualizar el tema.');
        }

        redirect('/settings');
    }

    private function wantsJson(): bool
    {
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strcasecmp((string) $xhr, 'XMLHttpRequest') === 0;
    }

    public function updateAreas(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $names = input('area_name');
        if (!is_array($names)) {
            $names = [];
        }
        $colors = input('area_color');
        if (!is_array($colors)) {
            $colors = [];
        }

        try {
            $updated = 0;
            foreach ($names as $areaId => $name) {
                $id = (int) $areaId;
                if ($id < 1) {
                    continue;
                }
                $this->areas->rename($userId, $id, (string) $name);
                if (isset($colors[$areaId])) {
                    $this->areas->recolor($userId, $id, (string) $colors[$areaId]);
                }
                $updated++;
            }

            $newAnnual = trim((string) input('new_annual_name', ''));
            if ($newAnnual !== '') {
                $this->areas->create($userId, 'annual', $newAnnual, (string) input('new_annual_color', '#7C83E1'));
                $updated++;
            }
            $newHorizon = trim((string) input('new_horizon_name', ''));
            if ($newHorizon !== '') {
                $this->areas->create($userId, 'horizon', $newHorizon, (string) input('new_horizon_color', '#4F46E5'));
                $updated++;
            }

            $this->areas->ensureCanonicalAreas($userId);
            $year = (int) now_local()->format('Y');
            (new AnnualPlanService())->ensureYear($userId, $year);

            $this->audit->log($userId, 'settings.areas', 'life_area', null, ['count' => $updated]);
            flash('success', 'Áreas actualizadas.');
        } catch (Throwable $e) {
            flash('error', 'No se pudieron guardar las áreas: ' . $e->getMessage());
        }

        redirect('/settings');
    }

    public function destroyArea(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        try {
            $this->areas->softDelete($userId, (int) $id);
            $this->audit->log($userId, 'settings.area_delete', 'life_area', (int) $id);
            flash('success', 'Área eliminada.');
        } catch (Throwable $e) {
            flash('error', 'No se pudo eliminar el área.');
        }
        redirect('/settings');
    }
}
