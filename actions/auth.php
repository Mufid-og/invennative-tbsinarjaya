<?php

// actions/auth.php
$action = $_GET['do'] ?? $_POST['do'] ?? 'login';

if ($action === 'logout') {
    log_aktivitas('LOGOUT', 'auth', auth_id(), 'User melakukan logout');
    auth_logout();
    redirect('/login');
}

// do_login (POST)
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($username === '') {
        $errors['username'] = 'Username wajib diisi.';
    }
    if ($password === '') {
        $errors['password'] = 'Password wajib diisi.';
    }

    if (empty($errors)) {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND deleted_at IS NULL');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            auth_login($user, $remember);
            log_aktivitas('LOGIN', 'auth', (int)$user['id'], 'User "' . $user['username'] . '" masuk');
            redirect('/dashboard');
        } else {
            $errors['username'] = 'Username atau password salah.';
        }
    }

    $_SESSION['login_errors'] = $errors;
    $_SESSION['login_old'] = ['username' => $username];
    redirect('/login');
}
