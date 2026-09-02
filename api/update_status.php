<?php
/**
 * api/update_status.php
 * ─────────────────────────────────────────────────────────────────────────────
 * AJAX endpoint for setting assessment final status:
 * - Assessment Completed ('PROCEED_TO_PROPOSAL')
 * - Aborted / On Hold    ('HOLD')
 * - Rejected / Not A Fit ('NOT_A_FIT')
 *
 * Updates status, logs audit event, recalculates SLA timer payload,
 * and returns JSON response.
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
$newStatus    = trim((string)($body['status'] ?? ''));

$allowedStatuses = [
    'PROCEED_TO_PROPOSAL' => [
        'label'       => 'Passed · Proceed to Proposal',
        'badge_class' => 'bg-emerald-950/80 text-emerald-300 border-emerald-500',
        'audit_msg'   => 'Assessment marked as Completed (Proceed to Proposal)'
    ],
    'HOLD' => [
        'label'       => 'Aborted · On Hold',
        'badge_class' => 'bg-amber-950/80 text-[#c9a84c] border-amber-500',
        'audit_msg'   => 'Assessment marked as Aborted / On Hold'
    ],
    'NOT_A_FIT' => [
        'label'       => 'Rejected · Not A Fit',
        'badge_class' => 'bg-red-950/80 text-red-300 border-red-500',
        'audit_msg'   => 'Assessment marked as Rejected (Not A Fit)'
    ],
];

if ($assessmentId <= 0 || !isset($allowedStatuses[$newStatus])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$pdo = getDbConnection();
ensureCheckReportColumns($pdo);

$statusInfo = $allowedStatuses[$newStatus];

$stmt = $pdo->prepare("
    UPDATE `assessments` 
    SET `status` = ?, 
        `last_updated_by_user_id` = ?, 
        `updated_at` = NOW() 
    WHERE `id` = ?
");
$stmt->execute([$newStatus, $currentUser['id'], $assessmentId]);

// Audit log
logAudit($assessmentId, 'ASSESSMENT_STATUS_UPDATED', [
    'new_status' => $newStatus,
    'status_label' => $statusInfo['label'],
    'updated_by' => $currentUser['name']
], (int)$currentUser['id']);

$updatedAssessment = getAssessmentDetails($assessmentId);
$slaStatus          = $updatedAssessment ? getAssessmentSlaStatus($updatedAssessment) : [];

echo json_encode([
    'success'            => true,
    'status'             => $newStatus,
    'status_label'       => $statusInfo['label'],
    'status_badge_class' => $statusInfo['badge_class'],
    'last_updated_by'    => $currentUser['name'],
    'last_updated_at'    => date('M j, Y H:i'),
    'sla'                => $slaStatus,
]);
