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

/**
 * Normalizes role names (case-insensitive, maps common aliases like project_manager -> pm).
 */
function normalizeRole(?string $role): string {
    $r = strtolower(trim((string)$role));
    if ($r === 'project_manager' || $r === 'project manager' || $r === 'project-manager') {
        return 'pm';
    }
    return $r;
}

/**
 * Checks if the current (or specified) user possesses any of the given roles.
 *
 * @param array|string $roles Allowed role(s), e.g. 'ceo', ['ceo', 'pm']
 * @param array|null $user Optional user array, defaults to getCurrentUser()
 * @return bool
 */
function hasRole(array|string $roles, ?array $user = null): bool {
    if ($user === null) {
        $user = getCurrentUser();
    }
    if (!$user || empty($user['role'])) {
        return false;
    }

    $userRole = normalizeRole($user['role']);
    $roleList = is_array($roles) ? $roles : [$roles];

    foreach ($roleList as $r) {
        if ($userRole === normalizeRole($r)) {
            return true;
        }
    }

    return false;
}

/**
 * Semantic convenience alias for hasRole().
 */
function canAccess(array|string $roles, ?array $user = null): bool {
    return hasRole($roles, $user);
}

/**
 * Page Controller Guard: Redirects if user is not logged in or lacks required role.
 */
function requireRole(array|string $allowedRoles, string $redirectUrl = '/ufc_v1/admin/assessments.php'): void {
    requireLogin();
    if (!hasRole($allowedRoles)) {
        header("Location: $redirectUrl?error=unauthorized");
        exit;
    }
}

/**
 * API Endpoint Guard: Blocks request and returns JSON error if unauthorized.
 * Responds with HTTP 401 (Unauthenticated) or HTTP 403 (Forbidden).
 */
function guardApi(array|string $allowedRoles): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Authentication required.']);
        exit;
    }

    if (!hasRole($allowedRoles)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden: Insufficient permissions.']);
        exit;
    }
}

/**
 * Conditionally executes a render callback or fallback closure based on user role.
 */
function renderIfRole(array|string $roles, callable $renderCallback, ?callable $fallbackCallback = null): void {
    if (hasRole($roles)) {
        $renderCallback();
    } elseif ($fallbackCallback !== null) {
        $fallbackCallback();
    }
}

/**
 * Conditionally includes a PHP component file based on user role.
 */
function renderComponentIfRole(array|string $roles, string $componentFile, array $variables = []): void {
    if (hasRole($roles) && file_exists($componentFile)) {
        extract($variables);
        require $componentFile;
    }
}

function isCeo(?array $user = null): bool {
    return hasRole('ceo', $user);
}

function isPm(?array $user = null): bool {
    return hasRole('pm', $user);
}

function isCeoOrPm(?array $user = null): bool {
    return hasRole(['ceo', 'pm'], $user);
}

function isAdmin(?array $user = null): bool {
    return hasRole(['admin', 'ceo'], $user);
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
