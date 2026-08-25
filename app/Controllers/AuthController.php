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
            flash('error', 'Credenciales incorrectas o cuenta inactiva.');
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
