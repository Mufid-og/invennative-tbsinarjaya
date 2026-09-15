<?php

declare(strict_types=1);

function auth_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function auth_login(array $user, bool $remember = false): void
{
    auth_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nama'] = $user['nama'];
    $_SESSION['user_username'] = $user['username'];
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $hashed = password_hash($token, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET remember_token = ? WHERE id = ?')->execute([$hashed, $user['id']]);
        setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
        setcookie('remember_user', (string)$user['id'], time() + (86400 * 30), '/', '', false, true);
    }
}

function auth_logout(): void
{
    auth_start();
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        db()->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')->execute([$userId]);
    }
    session_destroy();
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    if (isset($_COOKIE['remember_user'])) {
        setcookie('remember_user', '', time() - 3600, '/', '', false, true);
    }
}

function auth_check(): bool
{
    auth_start();
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    if (isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
        $userId = (int)$_COOKIE['remember_user'];
        $stmt = db()->prepare('SELECT id, username, nama, remember_token FROM users WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && $user['remember_token'] && password_verify($_COOKIE['remember_token'], $user['remember_token'])) {
            auth_login($user, false);
            return true;
        }
    }
    return false;
}

function auth_user(): ?array
{
    auth_start();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => (int)$_SESSION['user_id'],
        'nama' => $_SESSION['user_nama'] ?? '',
        'username' => $_SESSION['user_username'] ?? '',
    ];
}

function auth_id(): ?int
{
    auth_start();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function auth_guest(): bool
{
    return !auth_check();
}
