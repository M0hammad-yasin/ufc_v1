<?php
/**
 * api/update_milestones.php
 * ─────────────────────────────────────────────────────────────────────────────
 * AJAX endpoint for updating assessment progress milestones / checkboxes:
 * 1. Pre-Assessment (Phase 1 completion)
 * 2. Client Meetup
 * 3. Build Proposal
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

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?? $_POST;

$assessmentId = isset($body['assessment_id']) ? (int)$body['assessment_id'] : 0;
$checkpoint   = trim((string)($body['checkpoint'] ?? ''));
$value        = !empty($body['value']) && ($body['value'] === true || $body['value'] === 1 || $body['value'] === '1' || $body['value'] === 'true') ? 1 : 0;

$allowedCheckpoints = [
    'pre_assessment' => ['col' => 'checkpoint_pre_assessment', 'at_col' => 'checkpoint_pre_assessment_at', 'label' => 'Pre-Assessment'],
    'client_meetup'  => ['col' => 'checkpoint_client_meetup',  'at_col' => 'checkpoint_client_meetup_at',  'label' => 'Client Meetup'],
    'build_proposal' => ['col' => 'checkpoint_build_proposal', 'at_col' => 'checkpoint_build_proposal_at', 'label' => 'Build Proposal'],
];

if ($assessmentId <= 0 || !isset($allowedCheckpoints[$checkpoint])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$pdo = getDbConnection();
ensureCheckReportColumns($pdo);

$chkInfo = $allowedCheckpoints[$checkpoint];
$col = $chkInfo['col'];
$atCol = $chkInfo['at_col'];

$sql = "UPDATE `assessments` SET `{$col}` = ?, `{$atCol}` = " . ($value ? "NOW()" : "NULL") . ", `last_updated_by_user_id` = ? WHERE `id` = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$value, $currentUser['id'], $assessmentId]);

// Log change in audit history
logAudit($assessmentId, 'MILESTONE_UPDATED', [
    'milestone' => $chkInfo['label'],
    'completed' => (bool)$value,
    'updated_by' => $currentUser['name']
], (int)$currentUser['id']);

$updatedAssessment = getAssessmentDetails($assessmentId);
$slaStatus = $updatedAssessment ? getAssessmentSlaStatus($updatedAssessment) : [];

echo json_encode([
    'success'         => true,
    'checkpoint'      => $checkpoint,
    'value'           => (bool)$value,
    'checkpoint_at'   => $value ? date('M j, Y H:i') : null,
    'last_updated_by' => $currentUser['name'],
    'last_updated_at' => date('M j, Y H:i'),
    'sla'             => $slaStatus,
]);
