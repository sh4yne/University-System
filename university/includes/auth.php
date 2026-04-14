<?php
// includes/auth.php  — session helper
if (session_status() === PHP_SESSION_NONE) session_start();

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /university/login.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function isAdmin(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'admin';
}

function flash(string $key, string $msg = ''): string {
    if ($msg) {
        $_SESSION['flash'][$key] = $msg;
        return '';
    }
    $val = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $val;
}

function old(string $key, string $default = ''): string {
    $v = $_SESSION['old'][$key] ?? $default;
    unset($_SESSION['old'][$key]);
    return htmlspecialchars($v);
}

function sanitize(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}