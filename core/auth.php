<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/* ===============================
   Session Init
   =============================== */

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ===============================
   CSRF Helpers
   =============================== */

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_SESSION_KEY])) {
        $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_SESSION_KEY];
}

function verify_csrf(?string $token): bool
{
    return isset($_SESSION[CSRF_SESSION_KEY])
        && hash_equals($_SESSION[CSRF_SESSION_KEY], $token ?? '');
}

/* ===============================
   Authentication
   =============================== */

function login(string $email, string $password): bool
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.password, u.status, u.role AS role
        FROM users u
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && (int)$user['status'] === 1 && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role']; // admin / user / staff
        return true;
    }

    return false;
}

function register(string $name, string $email, string $password, string $phone)
{
    global $pdo;

    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $check->execute([$email]);

    if ($check->fetch()) {
        return 'Email already exists';
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, phone, role, status)
        VALUES (?, ?, ?, ?, 'user', 1)
    ");

    return $stmt->execute([$name, $email, $hash, $phone])
        ? true
        : 'Registration failed';
}

/* ===============================
   Guards & Helpers
   =============================== */

function logout(): void
{
    session_destroy();
    header('Location: /techhat/login.php');
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /techhat/login.php');
        exit;
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: /techhat/login.php');
        exit;
    }
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}
