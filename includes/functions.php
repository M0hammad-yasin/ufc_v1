<?php
/**
 * United Five Construction - Reusable Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

function sanitize(?string $str): string {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function formatDate(?string $dateStr, string $format = 'M j, Y'): string {
    if (!$dateStr) return 'N/A';
    try {
        $dt = new DateTime($dateStr);
        return $dt->format($format);
    } catch (Exception $e) {
        return (string)$dateStr;
    }
}

function setFlashMessage(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlashMessages(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function logAudit(int $assessmentId, string $action, array $details = [], ?int $userId = null): void {
    try {
        $pdo = getDbConnection();
        if ($userId === null && isset($_SESSION['user']['id'])) {
            $userId = (int)$_SESSION['user']['id'];
        }
        $stmt = $pdo->prepare("INSERT INTO `assessment_history` (`assessment_id`, `user_id`, `action`, `details`) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $assessmentId,
            $userId,
            $action,
            json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

function generateAssessmentNumber(): string {
    return 'UFC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function getAssessmentDetails(int $assessmentId): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM assessments WHERE id = ? LIMIT 1");
    $stmt->execute([$assessmentId]);
    $res = $stmt->fetch();
    return $res ?: null;
}
