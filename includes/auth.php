<?php
/**
 * United Five Construction - Authentication & Access Control
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function requireLogin(string $redirectUrl = '/ufc_v1/auth/login.php'): void {
    if (!isLoggedIn()) {
        header("Location: $redirectUrl");
        exit;
    }
}

function requireRole(array $allowedRoles, string $redirectUrl = '/ufc_v1/admin/assessments.php'): void {
    requireLogin();
    $user = getCurrentUser();
    if (!in_array($user['role'], $allowedRoles, true)) {
        header("Location: $redirectUrl?error=unauthorized");
        exit;
    }
}

function isCeo(): bool {
    $user = getCurrentUser();
    return $user && $user['role'] === 'ceo';
}

function isAdmin(): bool {
    $user = getCurrentUser();
    return $user && ($user['role'] === 'admin' || $user['role'] === 'ceo');
}

function loginUser(string $email, string $password): bool {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        return true;
    }
    return false;
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
