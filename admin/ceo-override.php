<?php
/**
 * United Five Construction - CEO Override Handler
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/evaluation.php';

requireRole(['ceo', 'admin']);
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/assessments.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($token)) {
    die("Security token invalid.");
}

$assessmentId = (int)($_POST['assessment_id'] ?? 0);
$phaseId = (int)($_POST['phase_id'] ?? 0);
$triggerType = trim($_POST['trigger_type'] ?? 'STOP');
$justification = trim($_POST['justification'] ?? '');

if (empty($justification) || $assessmentId <= 0 || $phaseId <= 0) {
    setFlashMessage('danger', 'Justification is required for executive override.');
    header('Location: ' . BASE_URL . '/admin/assessment.php?id=' . $assessmentId);
    exit;
}

$action = ($triggerType === 'ESCALATE') ? 'CLEAR_ESCALATION' : 'OVERRIDE_TO_PASS';

$pdo = getDbConnection();
$stmt = $pdo->prepare("
    INSERT INTO `ceo_overrides` (`assessment_id`, `phase_id`, `trigger_type`, `action`, `justification`, `ceo_user_id`)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $assessmentId,
    $phaseId,
    $triggerType,
    $action,
    $justification,
    $currentUser['id']
]);

// Find phase number
$stmtP = $pdo->prepare("SELECT phase_number FROM phases WHERE id = ?");
$stmtP->execute([$phaseId]);
$phase = $stmtP->fetch();
$phaseNum = $phase ? (int)$phase['phase_number'] : 1;

// Re-evaluate the phase gate with override applied
evaluatePhaseGate($assessmentId, $phaseNum, $currentUser['name']);

logAudit($assessmentId, 'CEO_OVERRIDE_RECORDED', [
    'trigger_type' => $triggerType,
    'phase_number' => $phaseNum,
    'justification' => $justification
], (int)$currentUser['id']);

setFlashMessage('success', "Executive override recorded permanently for Phase {$phaseNum}. Phase gate re-evaluated.");
header('Location: ' . BASE_URL . '/admin/assessment.php?id=' . $assessmentId);
exit;
