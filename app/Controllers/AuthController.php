<?php

declare(strict_types=1);

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/today');
        }

        view('auth/login', [
            'title' => 'Iniciar sesión',
            'error' => flash('error'),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        verify_csrf();

        $email = trim((string) input('email', ''));
        $password = (string) input('password', '');

        if ($email === '' || $password === '') {
            flash('error', 'Ingresá tu correo y contraseña.');
            redirect('/login');
        }

        if (!Auth::attempt($email, $password)) {
            if (Auth::isLoginLocked()) {
                flash('error', 'Demasiados intentos. Esperá unos minutos e intentá de nuevo.');
            } else {
                flash('error', 'Credenciales incorrectas o cuenta inactiva.');
            }
            redirect('/login');
        }

        redirect('/today');
    }

    public function logout(): void
    {
        verify_csrf();
        Auth::logout();
        redirect('/login');
    }
}
