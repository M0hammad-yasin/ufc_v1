<?php
/**
 * United Five Construction - CEO Override Handler
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/evaluation.php';

// Restricted to CEO and Executive Admin authority
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

$assessmentId  = (int)($_POST['assessment_id'] ?? 0);
$questionId    = (int)($_POST['question_id'] ?? 0);
$justification = trim($_POST['justification'] ?? '');

if ($assessmentId <= 0 || $questionId <= 0) {
    setFlashMessage('danger', 'Invalid assessment or trigger question specified.');
    header('Location: ' . BASE_URL . '/admin/assessments.php');
    exit;
}

if (empty($justification)) {
    setFlashMessage('danger', 'Written justification is mandatory for executive action.');
    header('Location: ' . BASE_URL . '/admin/assessment.php?id=' . $assessmentId);
    exit;
}

$pdo = getDbConnection();

// Server-side validation: do NOT trust client phase_id or trigger_type.
// Verify the target trigger actually exists on this assessment and is STOP or ESCALATE.
$stmt = $pdo->prepare("
    SELECT aa.*, q.question_number, q.question_text, q.phase_id, p.phase_number, p.title AS phase_title
    FROM assessment_answers aa
    JOIN questions q ON aa.question_id = q.id
    JOIN phases p ON q.phase_id = p.id
    WHERE aa.assessment_id = ? AND aa.question_id = ?
    LIMIT 1
");
$stmt->execute([$assessmentId, $questionId]);
$targetTrigger = $stmt->fetch();

if (!$targetTrigger || !in_array($targetTrigger['trigger_fired'], ['STOP', 'ESCALATE'], true)) {
    setFlashMessage('danger', 'No active executive trigger (STOP/ESCALATE) exists for this question.');
    header('Location: ' . BASE_URL . '/admin/assessment.php?id=' . $assessmentId);
    exit;
}

$triggerType = $targetTrigger['trigger_fired'];
$phaseId     = (int)$targetTrigger['phase_id'];
$phaseNum    = (int)$targetTrigger['phase_number'];
$qNumber     = $targetTrigger['question_number'];

// Prevent duplicate overrides/clearances for the same trigger
$stmtCheck = $pdo->prepare("
    SELECT id FROM `ceo_overrides`
    WHERE `assessment_id` = ?
      AND `trigger_type` = ?
      AND (`question_id` = ? OR (`question_id` IS NULL AND `phase_id` = ?))
    LIMIT 1
");
$stmtCheck->execute([$assessmentId, $triggerType, $questionId, $phaseId]);
if ($stmtCheck->fetch()) {
    setFlashMessage('warning', "Executive action has already been recorded for Question {$qNumber} ({$triggerType}).");
    header('Location: ' . BASE_URL . '/admin/assessment.php?id=' . $assessmentId);
    exit;
}

// Action type: STOP -> OVERRIDE_TO_PASS (clears the STOP barrier for gate eval, preserving original STOP answer)
// ESCALATE -> CLEAR_ESCALATION (clears the ESCALATION barrier for gate eval, preserving original ESCALATE answer)
$action = ($triggerType === 'ESCALATE') ? 'CLEAR_ESCALATION' : 'OVERRIDE_TO_PASS';

// Store override permanently with full context
$stmtInsert = $pdo->prepare("
    INSERT INTO `ceo_overrides` 
        (`assessment_id`, `phase_id`, `question_id`, `trigger_type`, `action`, `justification`, `ceo_user_id`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmtInsert->execute([
    $assessmentId,
    $phaseId,
    $questionId,
    $triggerType,
    $action,
    $justification,
    $currentUser['id']
]);

// Audit history logging
logAudit($assessmentId, 'CEO_OVERRIDE_RECORDED', [
    'trigger_type'    => $triggerType,
    'action'          => $action,
    'phase_number'    => $phaseNum,
    'question_number' => $qNumber,
    'justification'   => $justification,
], (int)$currentUser['id']);

// Re-evaluate affected phase gate (does NOT auto-pass; evaluates all remaining phase gate criteria)
evaluatePhaseGate($assessmentId, $phaseNum, $currentUser['name']);

$successMsg = ($triggerType === 'ESCALATE')
    ? "Escalation cleared for Question {$qNumber} (Phase {$phaseNum}). Phase gate re-evaluated."
    : "Executive STOP override recorded for Question {$qNumber} (Phase {$phaseNum}). Phase gate re-evaluated.";

setFlashMessage('success', $successMsg);
header('Location: ' . BASE_URL . '/admin/assessment.php?id=' . $assessmentId);
exit;
