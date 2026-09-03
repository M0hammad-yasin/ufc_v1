<?php
/**
 * api/delete_assessment.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Soft-deletes an assessment by setting `is_deleted` = 1.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUser = $_SESSION['user'] ?? null;
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorised']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? $_POST;

$assessmentId = isset($body['assessment_id']) ? (int)$body['assessment_id'] : 0;

if ($assessmentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid assessment ID']);
    exit;
}

$pdo = getDbConnection();
ensureCheckReportColumns($pdo);

// Verify assessment exists
$checkStmt = $pdo->prepare("SELECT id, assessment_number, client_name FROM assessments WHERE id = ?");
$checkStmt->execute([$assessmentId]);
$ass = $checkStmt->fetch(PDO::FETCH_ASSOC);

if (!$ass) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Assessment not found']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE `assessments` 
        SET `is_deleted` = 1,
            `last_updated_by_user_id` = ?,
            `updated_at` = NOW()
        WHERE `id` = ?
    ");
    $stmt->execute([$currentUser['id'], $assessmentId]);

    // Audit log
    logAudit($assessmentId, 'ASSESSMENT_DELETED', [
        'assessment_number' => $ass['assessment_number'],
        'client_name'       => $ass['client_name'],
        'deleted_by'        => $currentUser['name']
    ], (int)$currentUser['id']);

    echo json_encode([
        'success'       => true,
        'assessment_id' => $assessmentId,
        'message'       => "Assessment #{$ass['assessment_number']} has been deleted."
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete assessment: ' . $e->getMessage()]);
}
