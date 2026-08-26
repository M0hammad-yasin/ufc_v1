<?php
/**
 * United Five Construction - Save Answer & Dynamic Router
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/evaluation.php';
require_once __DIR__ . '/../includes/upload.php';

requireLogin();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /ufc_v1/admin/assessments.php");
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($token)) {
    die("Session token invalid. Please refresh the page.");
}

$assessmentId = (int)($_POST['assessment_id'] ?? 0);
$questionId = (int)($_POST['question_id'] ?? 0);
$questionNumber = trim($_POST['question_number'] ?? '');
$phaseNumber = (int)($_POST['phase_number'] ?? 1);

$assessment = getAssessmentDetails($assessmentId);
$question = getQuestionById($questionId);

if (!$assessment || !$question) {
    die("Assessment or question not found.");
}

// 1. Prepare Answer Data
$rawAnswer = $_POST['answer_value'] ?? null;
if ($question['response_type'] === 'MULTI_SELECT') {
    $rawAnswer = $_POST['checklist_checked'] ?? [];
}

$naJustification = trim($_POST['na_justification'] ?? '');
$notRequiredList = $_POST['not_required'] ?? [];

$explainReason = trim($_POST['explain_reason'] ?? '');
$explainParty = trim($_POST['explain_responsible_party'] ?? 'CLIENT');
$explainCureDate = trim($_POST['explain_target_cure_date'] ?? '');

$savePayload = [
    'answer_value' => $rawAnswer,
    'na_justification' => $naJustification,
    'not_required' => $notRequiredList,
    'explain_reason' => $explainReason,
    'explain_responsible_party' => $explainParty,
    'explain_target_cure_date' => $explainCureDate
];

// 2. Authoritative Backend Business Evaluation & Save
$evalResult = saveAnswerAndEvaluate($assessmentId, $questionId, $savePayload, (int)$currentUser['id']);

// 3. Handle File Upload if present
if (!empty($_FILES['evidence_file']['name'])) {
    $explainBlockId = getExplainBlockId($assessmentId, $questionId);
    $uploadRes = handleEvidenceUpload($assessmentId, $questionId, $explainBlockId, $_FILES['evidence_file'], (int)$currentUser['id']);
    if (!$uploadRes['success']) {
        setFlashMessage('warning', "Answer saved, but evidence upload failed: " . $uploadRes['error']);
    }
}

// 4. Calculate Dynamic Next Question in Phase
$nextQ = getNextApplicableQuestion($assessmentId, $phaseNumber, $questionNumber);

if ($nextQ) {
    // Navigate to next applicable question
    header("Location: /ufc_v1/assessment/question.php?id={$assessmentId}&q={$nextQ['question_number']}");
    exit;
} else {
    // All applicable questions in this phase are complete -> Run Phase Gatekeeper
    $gateResult = evaluatePhaseGate($assessmentId, $phaseNumber, $currentUser['name']);
    header("Location: /ufc_v1/assessment/phase-result.php?id={$assessmentId}&phase={$phaseNumber}");
    exit;
}
